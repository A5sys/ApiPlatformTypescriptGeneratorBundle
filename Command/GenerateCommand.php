<?php

namespace A5sys\ApiPlatformTypescriptGeneratorBundle\Command;

use A5sys\ApiPlatformTypescriptGeneratorBundle\Generator\GeneratorService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends Command
{
    private $generatorService;
    private $path;

    public function __construct(string $path, GeneratorService $generatorService)
    {
        $this->generatorService = $generatorService;
        $this->path = $path;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('generate:api-platform:ts-entities')
            ->setAliases(array('generate:api-platform:ts-entities'))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->generatorService->generate($this->path);
    }
}
