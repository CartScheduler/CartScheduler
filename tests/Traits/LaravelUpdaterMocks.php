<?php

namespace Tests\Traits;

use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubTagType;
use Mockery\MockInterface;

trait LaravelUpdaterMocks
{
    protected function makeGithubTagTypeMock(string $installedVersion = '1.0.0', string $availableVersion = '2.0.0'): MockInterface
    {
        return $this->partialMock(GithubTagType::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('getVersionInstalled')
            ->andReturn($installedVersion)
            ->shouldReceive('getVersionAvailable')
            ->andReturn($availableVersion)
            ->shouldReceive('isNewVersionAvailable')
            ->andReturn($availableVersion !== $installedVersion)
            ->shouldReceive('fetch')
            ->shouldReceive('update')
            ->andReturn($availableVersion !== $installedVersion)
        );
    }
}
