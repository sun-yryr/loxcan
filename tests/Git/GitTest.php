<?php

declare(strict_types=1);

namespace Siketyan\Loxcan\Git;

use Eloquent\Pathogen\Path;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Siketyan\Loxcan\Model\Repository;
use Symfony\Component\Process\Process;

class GitTest extends TestCase
{
    use ProphecyTrait;

    private ObjectProphecy $processFactory;
    private Git $git;

    protected function setUp(): void
    {
        $this->processFactory = $this->prophesize(GitProcessFactory::class);

        $this->git = new Git(
            $this->processFactory->reveal(),
        );
    }

    public function testFetchChangedFiles(): void
    {
        $base = 'master';
        $head = 'feature';
        $repository = $this->prophesize(Repository::class)->reveal();

        $process = $this->prophesize(Process::class);
        $process->run()->willReturn(0)->shouldBeCalledOnce();
        $process->isSuccessful()->willReturn(true);
        $process->getOutput()->willReturn(<<<'EOS'
foo/bar.json
baz.lock
EOS);

        $this->processFactory
            ->create($repository, ['diff', '--name-only', 'master..feature'])
            ->willReturn($process->reveal())
            ->shouldBeCalledOnce()
        ;

        $files = $this->git->fetchChangedFiles($repository, $base, $head);

        $this->assertSame(
            ['foo/bar.json', 'baz.lock'],
            $files,
        );
    }

    public function testFetchOriginalFile(): void
    {
        $repository = $this->prophesize(Repository::class)->reveal();
        $expected = <<<'EOS'
dummy
foobar
EOS;

        $process = $this->prophesize(Process::class);
        $process->run()->willReturn(0)->shouldBeCalledOnce();
        $process->isSuccessful()->willReturn(true);
        $process->getOutput()->willReturn($expected);

        $this->processFactory
            ->create($repository, ['show', 'master:bar.lock'])
            ->willReturn($process->reveal())
            ->shouldBeCalledOnce()
        ;

        $actual = $this->git->fetchOriginalFile($repository, 'master', 'bar.lock');

        $this->assertSame($expected, $actual);
    }

    public function testSupports(): void
    {
        $this->assertTrue(
            $this->git->supports(
                new Repository(
                    Path::fromString(__DIR__ . '/../..'),
                ),
            ),
        );
    }
}