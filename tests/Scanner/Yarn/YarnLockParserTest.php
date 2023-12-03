<?php

declare(strict_types=1);

namespace Siketyan\Loxcan\Scanner\Yarn;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Siketyan\Loxcan\Model\Dependency;
use Siketyan\Loxcan\Model\Package;
use Siketyan\Loxcan\Versioning\SemVer\SemVerVersion;
use Siketyan\Loxcan\Versioning\SemVer\SemVerVersionParser;

class YarnLockParserTest extends TestCase
{
    private const CONTENTS = <<<'EOS'
        # THIS IS AN AUTOGENERATED FILE. DO NOT EDIT THIS FILE DIRECTLY.
        # yarn lockfile v1


        "@types/node@^18":
          version "18.16.16"
          resolved "https://registry.yarnpkg.com/@types/node/-/node-18.16.16.tgz#3b64862856c7874ccf7439e6bab872d245c86d8e"
          integrity sha512-NpaM49IGQQAUlBhHMF82QH80J08os4ZmyF9MkpCzWAGuOHqE4gTEbhzd7L3l5LmWuZ6E0OiC1FweQ4tsiW35+g==

        typescript@^5:
          version "5.0.4"
          resolved "https://registry.yarnpkg.com/typescript/-/typescript-5.0.4.tgz#b217fd20119bd61a94d4011274e0ab369058da3b"
          integrity sha512-cW9T5W9xY37cc+jfEnaUvX91foxtHkza3Nw3wkoF4sSlKn0MONdkdEndig/qPBWXNkmplh3NzayQzCiHM4/hqw==
        EOS;

    private MockObject&YarnPackagePool $packagePool;
    private MockObject&SemVerVersionParser $versionParser;
    private YarnLockParser $parser;

    protected function setUp(): void
    {
        $this->packagePool = $this->createMock(YarnPackagePool::class);
        $this->versionParser = $this->createMock(SemVerVersionParser::class);

        $this->parser = new YarnLockParser(
            $this->packagePool,
            $this->versionParser,
        );
    }

    public function test(): void
    {
        $cache = $this->createStub(Package::class);
        $typesNodeVersion = $this->createStub(SemVerVersion::class);
        $typescriptVersion = $this->createStub(SemVerVersion::class);

        $this->packagePool->method('get')->willReturnCallback(fn (string $name): ?Stub => match ($name) {
            '@types/node' => null,
            'typescript' => $cache,
            default => $this->fail('unexpected pattern'),
        });

        $this->packagePool->expects($this->once())->method('add')->with($this->isInstanceOf(Package::class));

        $this->versionParser->method('parse')->willReturnMap([
            ['18.16.16', $typesNodeVersion],
            ['5.0.4', $typescriptVersion],
        ]);

        $collection = $this->parser->parse(self::CONTENTS);
        $dependencies = $collection->getDependencies();

        $this->assertCount(2, $dependencies);
        $this->assertContainsOnlyInstancesOf(Dependency::class, $dependencies);

        $this->assertSame('@types/node', $dependencies[0]->getPackage()->getName());
        $this->assertSame($typesNodeVersion, $dependencies[0]->getVersion());

        $this->assertSame($cache, $dependencies[1]->getPackage());
        $this->assertSame($typescriptVersion, $dependencies[1]->getVersion());
    }
}
