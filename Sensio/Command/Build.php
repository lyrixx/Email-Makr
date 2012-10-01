<?php

namespace Sensio\Command;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Twig_Error_Syntax;

use SplFileObject;

class Build extends BaseCommand
{
    private $twigBoot;

    public function __construct($twigBoot)
    {
      $this->twigBoot = $twigBoot;

      parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('generate-email')
            ->setDescription('Generate email with a default template and data from CSV')
            ->addArgument('template', InputArgument::REQUIRED, 'Base template')
            ->addArgument('csv', InputArgument::REQUIRED, 'Csv with data')
            ->addOption('output-directory', null, InputOption::VALUE_OPTIONAL, 'ouput directory', getcwd().DIRECTORY_SEPARATOR.'emailings')
            ->addOption('output-format', null, InputOption::VALUE_OPTIONAL, 'ouput format (leave a place holder)', 'mail_LANG.html')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $templateFile = $input->getArgument('template');
        try {
            $template = new SplFileObject($templateFile);
        } catch (\RuntimeException $e) {
            $output->writeln(sprintf('<error>Can not open "%s" in "%s"</error>', $templateFile, getcwd()));

            return 1;
        }

        $csvFile = $input->getArgument('csv');
        try {
            $csv = new SplFileObject($csvFile);
        } catch (\RuntimeException $e) {
            $output->writeln(sprintf('<error>Can not open "%s" in "%s"</error>', $csvFile, getcwd()));

            return 1;
        }

        $outputDirectory = $input->getOption('output-directory');
        if (!file_exists($outputDirectory) && !is_writable($outputDirectory)) {
            $output->writeln(sprintf('<error>Can not write in "%s"</error>', $outputDirectory));

            return 1;
        }

        $outputFormat = $input->getOption('output-format');
        if (false !== strpos('LANG', $outputFormat)) {
            $output->writeln(sprintf('<error>Place holder "LANG" is not found in "%s"</error>', $outputFormat));

            return 1;
        }
        $outputFormat = str_replace('LANG', '%s', $outputFormat);

        $twigBoot = $this->twigBoot;
        $twig = $twigBoot(dirname($template->getRealPath()));

        $csv->setFlags(SplFileObject::READ_CSV);
        $csv->setCsvControl(';', "\"");

        $varName = array();
        foreach ($csv as $lineNumber => $line) {
            $line = array_map('trim', $line);

            if (0 == $lineNumber) {
                unset($line[0]);
                $varName = $line;

                if (empty($varName)) {
                    $output->writeln('<error>Can not find variable name on line 1</error>');

                    return 1;
                }

                continue;
            }

            if (empty($line) || !$line[0]) {
                continue;
            }

            $lang = $line[0];
            unset($line[0]);

            if (count($line) != count($varName)) {
                $output->writeln(sprintf('<error>On line "%s", there are missing cells</error>', $lineNumber + 1));

                return 1;
            }

            $twigVar = array_combine($varName, $line);
            try {
                $twigOutput =  $twig->render($template, array_merge(array('lang' => $lang), $twigVar));
            } catch (Twig_Error_Syntax $e) {
                $output->writeln(sprintf('<error>There is one error in twig template \'%s\'. Error : \'%s\'</error>', $templateFile, $e->getMessage()));
            }
            $outputFile = sprintf('%s/'.$outputFormat, $outputDirectory, $lang);

            file_put_contents($outputFile, $twigOutput);

            $output->writeln(sprintf('<info>Generated "%s"</info>', $outputFile));
        }

        $output->writeln(sprintf('<info>Finished</info>', $outputFile));
    }
}
