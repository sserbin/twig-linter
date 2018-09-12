<?php
declare(strict_types=1);
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Sserbin\TwigLinter\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Loader\ArrayLoader;
use Twig\Source;

/**
 * Command that will validate your template syntax and output encountered errors.
 *
 * @author Marc Weistroff <marc.weistroff@sensiolabs.com>
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
class LintCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'lint';

    /** @var Environment */
    private $twig;

    public function __construct(Environment $twig)
    {
        parent::__construct();

        $this->twig = $twig;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Lints a template and outputs encountered errors')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'The output format', 'txt')
            ->addOption('ext', null, InputOption::VALUE_REQUIRED, 'Templates extension', 'twig')
            ->addArgument('filename', InputArgument::IS_ARRAY)
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command lints a template and outputs to STDOUT
the first encountered syntax error.

You can validate the syntax of contents passed from STDIN:

  <info>cat filename | php %command.full_name%</info>

Or the syntax of a file:

  <info>php %command.full_name% filename</info>

Or of a whole directory:

  <info>php %command.full_name% dirname</info>
  <info>php %command.full_name% dirname --format=json --ext=html</info>

EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string[] */
        $filenames = $input->getArgument('filename');

        if (0 === count($filenames)) {
            if (0 !== ftell(STDIN)) {
                throw new RuntimeException('Please provide a filename or pipe template content to STDIN.');
            }

            $template = '';
            while (!feof(STDIN)) {
                $template .= fread(STDIN, 1024);
            }

            return $this->display($input, $output, $io, array($this->validate($template, uniqid('sf_', true))));
        }

        /** @var string */
        $templatesExt = $input->getOption('ext');

        $filesInfo = $this->getFilesInfo($filenames, $templatesExt);

        return $this->display($input, $output, $io, $filesInfo);
    }

    /**
     * @param string[] $filenames
     * @return array<int,array{template:string,file:string,valid:bool,exception?:Error,line?:int}>
     */
    private function getFilesInfo(array $filenames, string $ext): array
    {
        $filesInfo = [];
        foreach ($filenames as $filename) {
            foreach ($this->findFiles($filename, $ext) as $file) {
                $file = (string) $file;
                $filesInfo[] = $this->validate(file_get_contents($file), $file);
            }
        }

        return $filesInfo;
    }

    /**
     * @return iterable<string|SplFileInfo>
     */
    protected function findFiles(string $filename, string $ext): iterable
    {
        if (is_file($filename)) {
            return [$filename];
        } elseif (is_dir($filename)) {
            /** @var iterable<SplFileInfo> */
            return Finder::create()->files()->in($filename)->name('*.' . $ext);
        }

        throw new RuntimeException(sprintf('File or directory "%s" is not readable', $filename));
    }

    /**
     * @return array{template:string,file:string,valid:bool,exception?:Error,line?:int}
     */
    private function validate(string $template, string $file): array
    {
        $realLoader = $this->twig->getLoader();
        try {
            $temporaryLoader = new ArrayLoader(array((string) $file => $template));
            $this->twig->setLoader($temporaryLoader);
            $nodeTree = $this->twig->parse($this->twig->tokenize(new Source($template, (string) $file)));
            $this->twig->compile($nodeTree);
            $this->twig->setLoader($realLoader);
        } catch (Error $e) {
            $this->twig->setLoader($realLoader);

            return [
                'template' => $template,
                'file' => $file,
                'line' => $e->getTemplateLine(),
                'valid' => false,
                'exception' => $e
            ];
        }

        return [
            'template' => $template,
            'file' => $file,
            'valid' => true
        ];
    }

    /**
     * @param array<int,array{template:string,file:string,valid:bool,exception?:Error,line?:int}> $files
     */
    private function display(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,
        array $files
    ): int {
        switch ($input->getOption('format')) {
            case 'txt':
                return $this->displayTxt($output, $io, $files);
            case 'json':
                return $this->displayJson($output, $files);
            default:
                throw new InvalidArgumentException(sprintf(
                    'The format "%s" is not supported.',
                    $input->getOption('format')
                ));
        }
    }

    /**
     * @param array<int,array{template:string,file:string,valid:bool,exception?:Error,line?:int}> $filesInfo
     */
    private function displayTxt(OutputInterface $output, SymfonyStyle $io, array $filesInfo): int
    {
        $errors = 0;

        foreach ($filesInfo as $info) {
            if ($info['valid'] && $output->isVerbose()) {
                $io->comment('<info>OK</info>' . ($info['file'] ? sprintf(' in %s', $info['file']) : ''));
            } elseif (!$info['valid']) {
                ++$errors;
                assert(isset($info['exception']));
                $this->renderException($io, $info['template'], $info['exception'], $info['file']);
            }
        }

        if (0 === $errors) {
            $io->success(sprintf('All %d Twig files contain valid syntax.', count($filesInfo)));
        } else {
            $io->warning(sprintf(
                '%d Twig files have valid syntax and %d contain errors.',
                count($filesInfo) - $errors,
                $errors
            ));
        }

        return min($errors, 1);
    }

    /**
     * @param array<int,array{template:string,file:string,valid:bool,exception?:Error,line?:int}> $filesInfo
     */
    private function displayJson(OutputInterface $output, array $filesInfo): int
    {
        $errors = 0;

        foreach ($filesInfo as $k => $v) {
            $filesInfo[$k]['file'] = (string) $v['file'];
            unset($filesInfo[$k]['template']);

            if (!$v['valid']) {
                assert(isset($v['exception']));
                $filesInfo[$k]['message'] = $v['exception']->getMessage();
                unset($filesInfo[$k]['exception']);
                $errors++;
            }
        }

        $output->writeln(json_encode($filesInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return min($errors, 1);
    }

    private function renderException(
        OutputInterface $output,
        string $template,
        Error $exception,
        ?string $file = null
    ): void {
        $line = $exception->getTemplateLine();

        if ($file) {
            /** @psalm-suppress UndefinedMethod */
            $output->text(sprintf('<error> ERROR </error> in %s (line %s)', $file, $line));
        } else {
            /** @psalm-suppress UndefinedMethod */
            $output->text(sprintf('<error> ERROR </error> (line %s)', $line));
        }

        foreach ($this->getContext($template, $line) as $lineNumber => $code) {
            /** @psalm-suppress UndefinedMethod */
            $output->text(sprintf(
                '%s %-6s %s',
                $lineNumber === $line ? '<error> >> </error>' : '    ',
                $lineNumber,
                $code
            ));
            if ($lineNumber === $line) {
                /** @psalm-suppress UndefinedMethod */
                $output->text(sprintf('<error> >> %s</error> ', $exception->getRawMessage()));
            }
        }
    }

    /**
     * @return array<int,string>
     */
    private function getContext(string $template, int $line, int $context = 3): array
    {
        $lines = explode("\n", $template);

        $position = max(0, $line - $context);
        $max = min(\count($lines), $line - 1 + $context);

        $result = array();
        while ($position < $max) {
            $result[$position + 1] = $lines[$position];
            ++$position;
        }

        return $result;
    }
}
