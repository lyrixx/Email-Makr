#!/usr/bin/env php
<?php

/*
Copyright (C) 2012 Grégoire Pineau

Permission is hereby granted, free of charge, to any person obtaining a
copy of this software and associated documentation files (the "Software"),
to deal in the Software without restriction, including without limitation
the rights to use, copy, modify, merge, publish, distribute, sublicense,
and/or sell copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUTOF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
IN THE SOFTWARE.

*/
 
namespace
{
class Pimple implements ArrayAccess
{
protected $values = array();
public function __construct (array $values = array())
{
$this->values = $values;
}
public function offsetSet($id, $value)
{
$this->values[$id] = $value;
}
public function offsetGet($id)
{
if (!array_key_exists($id, $this->values)) {
throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
}
$isFactory = is_object($this->values[$id]) && method_exists($this->values[$id],'__invoke');
return $isFactory ? $this->values[$id]($this) : $this->values[$id];
}
public function offsetExists($id)
{
return array_key_exists($id, $this->values);
}
public function offsetUnset($id)
{
unset($this->values[$id]);
}
public static function share(Closure $callable)
{
return function ($c) use ($callable) {
static $object;
if (null === $object) {
$object = $callable($c);
}
return $object;
};
}
public static function protect(Closure $callable)
{
return function ($c) use ($callable) {
return $callable;
};
}
public function raw($id)
{
if (!array_key_exists($id, $this->values)) {
throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
}
return $this->values[$id];
}
public function extend($id, Closure $callable)
{
if (!array_key_exists($id, $this->values)) {
throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
}
$factory = $this->values[$id];
if (!($factory instanceof Closure)) {
throw new InvalidArgumentException(sprintf('Identifier "%s" does not contain an object definition.', $id));
}
return $this->values[$id] = function ($c) use ($callable, $factory) {
return $callable($factory($c), $c);
};
}
public function keys()
{
return array_keys($this->values);
}
}
}
namespace Symfony\Component\Console\Command
{
use Symfony\Component\Console\Descriptor\TextDescriptor;
use Symfony\Component\Console\Descriptor\XmlDescriptor;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\HelperSet;
class Command
{
private $application;
private $name;
private $aliases;
private $definition;
private $help;
private $description;
private $ignoreValidationErrors;
private $applicationDefinitionMerged;
private $applicationDefinitionMergedWithArgs;
private $code;
private $synopsis;
private $helperSet;
public function __construct($name = null)
{
$this->definition = new InputDefinition();
$this->ignoreValidationErrors = false;
$this->applicationDefinitionMerged = false;
$this->applicationDefinitionMergedWithArgs = false;
$this->aliases = array();
if (null !== $name) {
$this->setName($name);
}
$this->configure();
if (!$this->name) {
throw new \LogicException('The command name cannot be empty.');
}
}
public function ignoreValidationErrors()
{
$this->ignoreValidationErrors = true;
}
public function setApplication(Application $application = null)
{
$this->application = $application;
if ($application) {
$this->setHelperSet($application->getHelperSet());
} else {
$this->helperSet = null;
}
}
public function setHelperSet(HelperSet $helperSet)
{
$this->helperSet = $helperSet;
}
public function getHelperSet()
{
return $this->helperSet;
}
public function getApplication()
{
return $this->application;
}
public function isEnabled()
{
return true;
}
protected function configure()
{
}
protected function execute(InputInterface $input, OutputInterface $output)
{
throw new \LogicException('You must override the execute() method in the concrete command class.');
}
protected function interact(InputInterface $input, OutputInterface $output)
{
}
protected function initialize(InputInterface $input, OutputInterface $output)
{
}
public function run(InputInterface $input, OutputInterface $output)
{
$this->getSynopsis();
$this->mergeApplicationDefinition();
try {
$input->bind($this->definition);
} catch (\Exception $e) {
if (!$this->ignoreValidationErrors) {
throw $e;
}
}
$this->initialize($input, $output);
if ($input->isInteractive()) {
$this->interact($input, $output);
}
$input->validate();
if ($this->code) {
$statusCode = call_user_func($this->code, $input, $output);
} else {
$statusCode = $this->execute($input, $output);
}
return is_numeric($statusCode) ? (int) $statusCode : 0;
}
public function setCode($code)
{
if (!is_callable($code)) {
throw new \InvalidArgumentException('Invalid callable provided to Command::setCode.');
}
$this->code = $code;
return $this;
}
public function mergeApplicationDefinition($mergeArgs = true)
{
if (null === $this->application || (true === $this->applicationDefinitionMerged && ($this->applicationDefinitionMergedWithArgs || !$mergeArgs))) {
return;
}
if ($mergeArgs) {
$currentArguments = $this->definition->getArguments();
$this->definition->setArguments($this->application->getDefinition()->getArguments());
$this->definition->addArguments($currentArguments);
}
$this->definition->addOptions($this->application->getDefinition()->getOptions());
$this->applicationDefinitionMerged = true;
if ($mergeArgs) {
$this->applicationDefinitionMergedWithArgs = true;
}
}
public function setDefinition($definition)
{
if ($definition instanceof InputDefinition) {
$this->definition = $definition;
} else {
$this->definition->setDefinition($definition);
}
$this->applicationDefinitionMerged = false;
return $this;
}
public function getDefinition()
{
return $this->definition;
}
public function getNativeDefinition()
{
return $this->getDefinition();
}
public function addArgument($name, $mode = null, $description ='', $default = null)
{
$this->definition->addArgument(new InputArgument($name, $mode, $description, $default));
return $this;
}
public function addOption($name, $shortcut = null, $mode = null, $description ='', $default = null)
{
$this->definition->addOption(new InputOption($name, $shortcut, $mode, $description, $default));
return $this;
}
public function setName($name)
{
$this->validateName($name);
$this->name = $name;
return $this;
}
public function getName()
{
return $this->name;
}
public function setDescription($description)
{
$this->description = $description;
return $this;
}
public function getDescription()
{
return $this->description;
}
public function setHelp($help)
{
$this->help = $help;
return $this;
}
public function getHelp()
{
return $this->help;
}
public function getProcessedHelp()
{
$name = $this->name;
$placeholders = array('%command.name%','%command.full_name%');
$replacements = array(
$name,
$_SERVER['PHP_SELF'].' '.$name
);
return str_replace($placeholders, $replacements, $this->getHelp());
}
public function setAliases($aliases)
{
foreach ($aliases as $alias) {
$this->validateName($alias);
}
$this->aliases = $aliases;
return $this;
}
public function getAliases()
{
return $this->aliases;
}
public function getSynopsis()
{
if (null === $this->synopsis) {
$this->synopsis = trim(sprintf('%s %s', $this->name, $this->definition->getSynopsis()));
}
return $this->synopsis;
}
public function getHelper($name)
{
return $this->helperSet->get($name);
}
public function asText()
{
$descriptor = new TextDescriptor();
return $descriptor->describe($this);
}
public function asXml($asDom = false)
{
$descriptor = new XmlDescriptor();
return $descriptor->describe($this, array('as_dom'=> $asDom));
}
private function validateName($name)
{
if (!preg_match('/^[^\:]+(\:[^\:]+)*$/', $name)) {
throw new \InvalidArgumentException(sprintf('Command name "%s" is invalid.', $name));
}
}
}
}
namespace Sensio\Command
{
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
->addArgument('template', InputArgument::REQUIRED,'Base template')
->addArgument('csv', InputArgument::REQUIRED,'Csv with data')
->addOption('output-directory', null, InputOption::VALUE_OPTIONAL,'ouput directory', getcwd().DIRECTORY_SEPARATOR.'emailings')
->addOption('output-format', null, InputOption::VALUE_OPTIONAL,'ouput format (leave a place holder)','mail_LANG.html')
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
$outputFormat = str_replace('LANG','%s', $outputFormat);
$twigBoot = $this->twigBoot;
$twig = $twigBoot(dirname($template->getRealPath()));
$csv->setFlags(SplFileObject::READ_CSV);
$csv->setCsvControl(';',"\"");
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
$twigOutput = $twig->render($template->getPathName(), array_merge(array('lang'=> $lang), $twigVar));
} catch (Twig_Error_Syntax $e) {
$output->writeln(sprintf('<error>There is one error in twig template \'%s\'. Error : \'%s\'</error>', $templateFile, $e->getMessage()));
}
$outputFile = sprintf('%s/'.$outputFormat, $outputDirectory, $lang);
file_put_contents($outputFile, $twigOutput);
$output->writeln(sprintf('<comment>Generated "%s"</comment>', $outputFile));
}
$output->writeln(sprintf('<info>Finished</info>', $outputFile));
return 0;
}
}
}
namespace Symfony\Component\Console
{
use Symfony\Component\Console\Descriptor\TextDescriptor;
use Symfony\Component\Console\Descriptor\XmlDescriptor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Helper\ProgressHelper;
use Symfony\Component\Console\Helper\TableHelper;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleExceptionEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
class Application
{
private $commands;
private $wantHelps = false;
private $runningCommand;
private $name;
private $version;
private $catchExceptions;
private $autoExit;
private $definition;
private $helperSet;
private $dispatcher;
public function __construct($name ='UNKNOWN', $version ='UNKNOWN')
{
$this->name = $name;
$this->version = $version;
$this->catchExceptions = true;
$this->autoExit = true;
$this->commands = array();
$this->helperSet = $this->getDefaultHelperSet();
$this->definition = $this->getDefaultInputDefinition();
foreach ($this->getDefaultCommands() as $command) {
$this->add($command);
}
}
public function setDispatcher(EventDispatcherInterface $dispatcher)
{
$this->dispatcher = $dispatcher;
}
public function run(InputInterface $input = null, OutputInterface $output = null)
{
if (null === $input) {
$input = new ArgvInput();
}
if (null === $output) {
$output = new ConsoleOutput();
}
$this->configureIO($input, $output);
try {
$exitCode = $this->doRun($input, $output);
} catch (\Exception $e) {
if (!$this->catchExceptions) {
throw $e;
}
if ($output instanceof ConsoleOutputInterface) {
$this->renderException($e, $output->getErrorOutput());
} else {
$this->renderException($e, $output);
}
$exitCode = $e->getCode();
$exitCode = $exitCode ? (is_numeric($exitCode) ? (int) $exitCode : 1) : 0;
}
if ($this->autoExit) {
if ($exitCode > 255) {
$exitCode = 255;
}
exit($exitCode);
}
return $exitCode;
}
public function doRun(InputInterface $input, OutputInterface $output)
{
if (true === $input->hasParameterOption(array('--version','-V'))) {
$output->writeln($this->getLongVersion());
return 0;
}
$name = $this->getCommandName($input);
if (true === $input->hasParameterOption(array('--help','-h'))) {
if (!$name) {
$name ='help';
$input = new ArrayInput(array('command'=>'help'));
} else {
$this->wantHelps = true;
}
}
if (!$name) {
$name ='list';
$input = new ArrayInput(array('command'=>'list'));
}
$command = $this->find($name);
$this->runningCommand = $command;
$exitCode = $this->doRunCommand($command, $input, $output);
$this->runningCommand = null;
return $exitCode;
}
public function setHelperSet(HelperSet $helperSet)
{
$this->helperSet = $helperSet;
}
public function getHelperSet()
{
return $this->helperSet;
}
public function setDefinition(InputDefinition $definition)
{
$this->definition = $definition;
}
public function getDefinition()
{
return $this->definition;
}
public function getHelp()
{
$messages = array(
$this->getLongVersion(),'','<comment>Usage:</comment>','  [options] command [arguments]','','<comment>Options:</comment>',
);
foreach ($this->getDefinition()->getOptions() as $option) {
$messages[] = sprintf('  %-29s %s %s','<info>--'.$option->getName().'</info>',
$option->getShortcut() ?'<info>-'.$option->getShortcut().'</info>':'  ',
$option->getDescription()
);
}
return implode(PHP_EOL, $messages);
}
public function setCatchExceptions($boolean)
{
$this->catchExceptions = (Boolean) $boolean;
}
public function setAutoExit($boolean)
{
$this->autoExit = (Boolean) $boolean;
}
public function getName()
{
return $this->name;
}
public function setName($name)
{
$this->name = $name;
}
public function getVersion()
{
return $this->version;
}
public function setVersion($version)
{
$this->version = $version;
}
public function getLongVersion()
{
if ('UNKNOWN'!== $this->getName() &&'UNKNOWN'!== $this->getVersion()) {
return sprintf('<info>%s</info> version <comment>%s</comment>', $this->getName(), $this->getVersion());
}
return'<info>Console Tool</info>';
}
public function register($name)
{
return $this->add(new Command($name));
}
public function addCommands(array $commands)
{
foreach ($commands as $command) {
$this->add($command);
}
}
public function add(Command $command)
{
$command->setApplication($this);
if (!$command->isEnabled()) {
$command->setApplication(null);
return;
}
$this->commands[$command->getName()] = $command;
foreach ($command->getAliases() as $alias) {
$this->commands[$alias] = $command;
}
return $command;
}
public function get($name)
{
if (!isset($this->commands[$name])) {
throw new \InvalidArgumentException(sprintf('The command "%s" does not exist.', $name));
}
$command = $this->commands[$name];
if ($this->wantHelps) {
$this->wantHelps = false;
$helpCommand = $this->get('help');
$helpCommand->setCommand($command);
return $helpCommand;
}
return $command;
}
public function has($name)
{
return isset($this->commands[$name]);
}
public function getNamespaces()
{
$namespaces = array();
foreach ($this->commands as $command) {
$namespaces[] = $this->extractNamespace($command->getName());
foreach ($command->getAliases() as $alias) {
$namespaces[] = $this->extractNamespace($alias);
}
}
return array_values(array_unique(array_filter($namespaces)));
}
public function findNamespace($namespace)
{
$allNamespaces = $this->getNamespaces();
$found ='';
foreach (explode(':', $namespace) as $i => $part) {
$namespaces = array();
foreach ($allNamespaces as $n) {
if (''=== $found || 0 === strpos($n, $found)) {
$namespaces[$n] = explode(':', $n);
}
}
$abbrevs = static::getAbbreviations(array_unique(array_values(array_filter(array_map(function ($p) use ($i) { return isset($p[$i]) ? $p[$i] :''; }, $namespaces)))));
if (!isset($abbrevs[$part])) {
$message = sprintf('There are no commands defined in the "%s" namespace.', $namespace);
if (1 <= $i) {
$part = $found.':'.$part;
}
if ($alternatives = $this->findAlternativeNamespace($part, $abbrevs)) {
if (1 == count($alternatives)) {
$message .="\n\nDid you mean this?\n    ";
} else {
$message .="\n\nDid you mean one of these?\n    ";
}
$message .= implode("\n    ", $alternatives);
}
throw new \InvalidArgumentException($message);
}
if (in_array($part, $abbrevs[$part])) {
$abbrevs[$part] = array($part);
}
if (count($abbrevs[$part]) > 1) {
throw new \InvalidArgumentException(sprintf('The namespace "%s" is ambiguous (%s).', $namespace, $this->getAbbreviationSuggestions($abbrevs[$part])));
}
$found .= $found ?':'. $abbrevs[$part][0] : $abbrevs[$part][0];
}
return $found;
}
public function find($name)
{
$namespace ='';
$searchName = $name;
if (false !== $pos = strrpos($name,':')) {
$namespace = $this->findNamespace(substr($name, 0, $pos));
$searchName = $namespace.substr($name, $pos);
}
$commands = array();
foreach ($this->commands as $command) {
$extractedNamespace = $this->extractNamespace($command->getName());
if ($extractedNamespace === $namespace
|| !empty($namespace) && 0 === strpos($extractedNamespace, $namespace)
) {
$commands[] = $command->getName();
}
}
$abbrevs = static::getAbbreviations(array_unique($commands));
if (isset($abbrevs[$searchName]) && 1 == count($abbrevs[$searchName])) {
return $this->get($abbrevs[$searchName][0]);
}
if (isset($abbrevs[$searchName]) && in_array($searchName, $abbrevs[$searchName])) {
return $this->get($searchName);
}
if (isset($abbrevs[$searchName]) && count($abbrevs[$searchName]) > 1) {
$suggestions = $this->getAbbreviationSuggestions($abbrevs[$searchName]);
throw new \InvalidArgumentException(sprintf('Command "%s" is ambiguous (%s).', $name, $suggestions));
}
$aliases = array();
foreach ($this->commands as $command) {
foreach ($command->getAliases() as $alias) {
$extractedNamespace = $this->extractNamespace($alias);
if ($extractedNamespace === $namespace
|| !empty($namespace) && 0 === strpos($extractedNamespace, $namespace)
) {
$aliases[] = $alias;
}
}
}
$aliases = static::getAbbreviations(array_unique($aliases));
if (!isset($aliases[$searchName])) {
$message = sprintf('Command "%s" is not defined.', $name);
if ($alternatives = $this->findAlternativeCommands($searchName, $abbrevs)) {
if (1 == count($alternatives)) {
$message .="\n\nDid you mean this?\n    ";
} else {
$message .="\n\nDid you mean one of these?\n    ";
}
$message .= implode("\n    ", $alternatives);
}
throw new \InvalidArgumentException($message);
}
if (count($aliases[$searchName]) > 1) {
throw new \InvalidArgumentException(sprintf('Command "%s" is ambiguous (%s).', $name, $this->getAbbreviationSuggestions($aliases[$searchName])));
}
return $this->get($aliases[$searchName][0]);
}
public function all($namespace = null)
{
if (null === $namespace) {
return $this->commands;
}
$commands = array();
foreach ($this->commands as $name => $command) {
if ($namespace === $this->extractNamespace($name, substr_count($namespace,':') + 1)) {
$commands[$name] = $command;
}
}
return $commands;
}
public static function getAbbreviations($names)
{
$abbrevs = array();
foreach ($names as $name) {
for ($len = strlen($name); $len > 0; --$len) {
$abbrev = substr($name, 0, $len);
$abbrevs[$abbrev][] = $name;
}
}
return $abbrevs;
}
public function asText($namespace = null, $raw = false)
{
$descriptor = new TextDescriptor();
return $descriptor->describe($this, array('namespace'=> $namespace,'raw_text'=> $raw));
}
public function asXml($namespace = null, $asDom = false)
{
$descriptor = new XmlDescriptor();
return $descriptor->describe($this, array('namespace'=> $namespace,'as_dom'=> $asDom));
}
public function renderException($e, $output)
{
$strlen = function ($string) {
if (!function_exists('mb_strlen')) {
return strlen($string);
}
if (false === $encoding = mb_detect_encoding($string)) {
return strlen($string);
}
return mb_strlen($string, $encoding);
};
do {
$title = sprintf('  [%s]  ', get_class($e));
$len = $strlen($title);
$width = $this->getTerminalWidth() ? $this->getTerminalWidth() - 1 : PHP_INT_MAX;
$lines = array();
foreach (preg_split('/\r?\n/', $e->getMessage()) as $line) {
foreach (str_split($line, $width - 4) as $line) {
$lines[] = sprintf('  %s  ', $line);
$len = max($strlen($line) + 4, $len);
}
}
$messages = array(str_repeat(' ', $len), $title.str_repeat(' ', max(0, $len - $strlen($title))));
foreach ($lines as $line) {
$messages[] = $line.str_repeat(' ', $len - $strlen($line));
}
$messages[] = str_repeat(' ', $len);
$output->writeln("");
$output->writeln("");
foreach ($messages as $message) {
$output->writeln('<error>'.$message.'</error>');
}
$output->writeln("");
$output->writeln("");
if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
$output->writeln('<comment>Exception trace:</comment>');
$trace = $e->getTrace();
array_unshift($trace, array('function'=>'','file'=> $e->getFile() != null ? $e->getFile() :'n/a','line'=> $e->getLine() != null ? $e->getLine() :'n/a','args'=> array(),
));
for ($i = 0, $count = count($trace); $i < $count; $i++) {
$class = isset($trace[$i]['class']) ? $trace[$i]['class'] :'';
$type = isset($trace[$i]['type']) ? $trace[$i]['type'] :'';
$function = $trace[$i]['function'];
$file = isset($trace[$i]['file']) ? $trace[$i]['file'] :'n/a';
$line = isset($trace[$i]['line']) ? $trace[$i]['line'] :'n/a';
$output->writeln(sprintf(' %s%s%s() at <info>%s:%s</info>', $class, $type, $function, $file, $line));
}
$output->writeln("");
$output->writeln("");
}
} while ($e = $e->getPrevious());
if (null !== $this->runningCommand) {
$output->writeln(sprintf('<info>%s</info>', sprintf($this->runningCommand->getSynopsis(), $this->getName())));
$output->writeln("");
$output->writeln("");
}
}
protected function getTerminalWidth()
{
$dimensions = $this->getTerminalDimensions();
return $dimensions[0];
}
protected function getTerminalHeight()
{
$dimensions = $this->getTerminalDimensions();
return $dimensions[1];
}
public function getTerminalDimensions()
{
if (defined('PHP_WINDOWS_VERSION_BUILD')) {
if (preg_match('/^(\d+)x\d+ \(\d+x(\d+)\)$/', trim(getenv('ANSICON')), $matches)) {
return array((int) $matches[1], (int) $matches[2]);
}
if (preg_match('/^(\d+)x(\d+)$/', $this->getConsoleMode(), $matches)) {
return array((int) $matches[1], (int) $matches[2]);
}
}
if ($sttyString = $this->getSttyColumns()) {
if (preg_match('/rows.(\d+);.columns.(\d+);/i', $sttyString, $matches)) {
return array((int) $matches[2], (int) $matches[1]);
}
if (preg_match('/;.(\d+).rows;.(\d+).columns/i', $sttyString, $matches)) {
return array((int) $matches[2], (int) $matches[1]);
}
}
return array(null, null);
}
protected function configureIO(InputInterface $input, OutputInterface $output)
{
if (true === $input->hasParameterOption(array('--ansi'))) {
$output->setDecorated(true);
} elseif (true === $input->hasParameterOption(array('--no-ansi'))) {
$output->setDecorated(false);
}
if (true === $input->hasParameterOption(array('--no-interaction','-n'))) {
$input->setInteractive(false);
}
if (function_exists('posix_isatty') && $this->getHelperSet()->has('dialog')) {
$inputStream = $this->getHelperSet()->get('dialog')->getInputStream();
if (!posix_isatty($inputStream)) {
$input->setInteractive(false);
}
}
if (true === $input->hasParameterOption(array('--quiet','-q'))) {
$output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
} else {
if ($input->hasParameterOption('-vvv') || $input->hasParameterOption('--verbose=3') || $input->getParameterOption('--verbose') === 3) {
$output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
} elseif ($input->hasParameterOption('-vv') || $input->hasParameterOption('--verbose=2') || $input->getParameterOption('--verbose') === 2) {
$output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
} elseif ($input->hasParameterOption('-v') || $input->hasParameterOption('--verbose=1') || $input->hasParameterOption('--verbose') || $input->getParameterOption('--verbose')) {
$output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
}
}
}
protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output)
{
if (null === $this->dispatcher) {
return $command->run($input, $output);
}
$event = new ConsoleCommandEvent($command, $input, $output);
$this->dispatcher->dispatch(ConsoleEvents::COMMAND, $event);
try {
$exitCode = $command->run($input, $output);
} catch (\Exception $e) {
$event = new ConsoleTerminateEvent($command, $input, $output, $e->getCode());
$this->dispatcher->dispatch(ConsoleEvents::TERMINATE, $event);
$event = new ConsoleExceptionEvent($command, $input, $output, $e, $event->getExitCode());
$this->dispatcher->dispatch(ConsoleEvents::EXCEPTION, $event);
throw $event->getException();
}
$event = new ConsoleTerminateEvent($command, $input, $output, $exitCode);
$this->dispatcher->dispatch(ConsoleEvents::TERMINATE, $event);
return $event->getExitCode();
}
protected function getCommandName(InputInterface $input)
{
return $input->getFirstArgument();
}
protected function getDefaultInputDefinition()
{
return new InputDefinition(array(
new InputArgument('command', InputArgument::REQUIRED,'The command to execute'),
new InputOption('--help','-h', InputOption::VALUE_NONE,'Display this help message.'),
new InputOption('--quiet','-q', InputOption::VALUE_NONE,'Do not output any message.'),
new InputOption('--verbose','-v|vv|vvv', InputOption::VALUE_NONE,'Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug'),
new InputOption('--version','-V', InputOption::VALUE_NONE,'Display this application version.'),
new InputOption('--ansi','', InputOption::VALUE_NONE,'Force ANSI output.'),
new InputOption('--no-ansi','', InputOption::VALUE_NONE,'Disable ANSI output.'),
new InputOption('--no-interaction','-n', InputOption::VALUE_NONE,'Do not ask any interactive question.'),
));
}
protected function getDefaultCommands()
{
return array(new HelpCommand(), new ListCommand());
}
protected function getDefaultHelperSet()
{
return new HelperSet(array(
new FormatterHelper(),
new DialogHelper(),
new ProgressHelper(),
new TableHelper(),
));
}
private function getSttyColumns()
{
if (!function_exists('proc_open')) {
return;
}
$descriptorspec = array(1 => array('pipe','w'), 2 => array('pipe','w'));
$process = proc_open('stty -a | grep columns', $descriptorspec, $pipes, null, null, array('suppress_errors'=> true));
if (is_resource($process)) {
$info = stream_get_contents($pipes[1]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($process);
return $info;
}
}
private function getConsoleMode()
{
if (!function_exists('proc_open')) {
return;
}
$descriptorspec = array(1 => array('pipe','w'), 2 => array('pipe','w'));
$process = proc_open('mode CON', $descriptorspec, $pipes, null, null, array('suppress_errors'=> true));
if (is_resource($process)) {
$info = stream_get_contents($pipes[1]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($process);
if (preg_match('/--------+\r?\n.+?(\d+)\r?\n.+?(\d+)\r?\n/', $info, $matches)) {
return $matches[2].'x'.$matches[1];
}
}
}
private function getAbbreviationSuggestions($abbrevs)
{
return sprintf('%s, %s%s', $abbrevs[0], $abbrevs[1], count($abbrevs) > 2 ? sprintf(' and %d more', count($abbrevs) - 2) :'');
}
public function extractNamespace($name, $limit = null)
{
$parts = explode(':', $name);
array_pop($parts);
return implode(':', null === $limit ? $parts : array_slice($parts, 0, $limit));
}
private function findAlternativeCommands($name, $abbrevs)
{
$callback = function($item) {
return $item->getName();
};
return $this->findAlternatives($name, $this->commands, $abbrevs, $callback);
}
private function findAlternativeNamespace($name, $abbrevs)
{
return $this->findAlternatives($name, $this->getNamespaces(), $abbrevs);
}
private function findAlternatives($name, $collection, $abbrevs, $callback = null)
{
$alternatives = array();
foreach ($collection as $item) {
if (null !== $callback) {
$item = call_user_func($callback, $item);
}
$lev = levenshtein($name, $item);
if ($lev <= strlen($name) / 3 || false !== strpos($item, $name)) {
$alternatives[$item] = $lev;
}
}
if (!$alternatives) {
foreach ($abbrevs as $key => $values) {
$lev = levenshtein($name, $key);
if ($lev <= strlen($name) / 3 || false !== strpos($key, $name)) {
foreach ($values as $value) {
$alternatives[$value] = $lev;
}
}
}
}
asort($alternatives);
return array_keys($alternatives);
}
}
}
namespace Symfony\Component\Console\Command
{
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
class HelpCommand extends Command
{
private $command;
protected function configure()
{
$this->ignoreValidationErrors();
$this
->setName('help')
->setDefinition(array(
new InputArgument('command_name', InputArgument::OPTIONAL,'The command name','help'),
new InputOption('xml', null, InputOption::VALUE_NONE,'To output help as XML'),
new InputOption('format', null, InputOption::VALUE_REQUIRED,'To output help in other formats'),
new InputOption('raw', null, InputOption::VALUE_NONE,'To output raw command help'),
))
->setDescription('Displays help for a command')
->setHelp(<<<EOF
The <info>%command.name%</info> command displays help for a given command:

  <info>php %command.full_name% list</info>

You can also output the help in other formats by using the <comment>--format</comment> option:

  <info>php %command.full_name% --format=xml list</info>

To display the list of available commands, please use the <info>list</info> command.
EOF
)
;
}
public function setCommand(Command $command)
{
$this->command = $command;
}
protected function execute(InputInterface $input, OutputInterface $output)
{
if (null === $this->command) {
$this->command = $this->getApplication()->find($input->getArgument('command_name'));
}
if ($input->getOption('xml')) {
$input->setOption('format','xml');
}
$helper = new DescriptorHelper();
$helper->describe($output, $this->command, $input->getOption('format'), $input->getOption('raw'));
$this->command = null;
}
}
}
namespace Symfony\Component\Console\Command
{
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputDefinition;
class ListCommand extends Command
{
protected function configure()
{
$this
->setName('list')
->setDefinition($this->createDefinition())
->setDescription('Lists commands')
->setHelp(<<<EOF
The <info>%command.name%</info> command lists all commands:

  <info>php %command.full_name%</info>

You can also display the commands for a specific namespace:

  <info>php %command.full_name% test</info>

You can also output the information in other formats by using the <comment>--format</comment> option:

  <info>php %command.full_name% --format=xml</info>

It's also possible to get raw list of commands (useful for embedding command runner):

  <info>php %command.full_name% --raw</info>
EOF
)
;
}
public function getNativeDefinition()
{
return $this->createDefinition();
}
protected function execute(InputInterface $input, OutputInterface $output)
{
if ($input->getOption('xml')) {
$input->setOption('format','xml');
}
$helper = new DescriptorHelper();
$helper->describe($output, $this->getApplication(), $input->getOption('format'), $input->getOption('raw'), $input->getArgument('namespace'));
}
private function createDefinition()
{
return new InputDefinition(array(
new InputArgument('namespace', InputArgument::OPTIONAL,'The namespace name'),
new InputOption('xml', null, InputOption::VALUE_NONE,'To output list as XML'),
new InputOption('raw', null, InputOption::VALUE_NONE,'To output raw command list'),
new InputOption('format', null, InputOption::VALUE_REQUIRED,'To output list in other formats'),
));
}
}
}
namespace Symfony\Component\Console\Descriptor
{
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
class ApplicationDescription
{
const GLOBAL_NAMESPACE ='_global';
private $application;
private $namespace;
private $namespaces;
private $commands;
private $aliases;
public function __construct(Application $application, $namespace = null)
{
$this->application = $application;
$this->namespace = $namespace;
}
public function getNamespaces()
{
if (null === $this->namespaces) {
$this->inspectApplication();
}
return $this->namespaces;
}
public function getCommands()
{
if (null === $this->commands) {
$this->inspectApplication();
}
return $this->commands;
}
public function getCommand($name)
{
if (!isset($this->commands[$name]) && !isset($this->aliases[$name])) {
throw new \InvalidArgumentException(sprintf('Command %s does not exist.', $name));
}
return isset($this->commands[$name]) ? $this->commands[$name] : $this->aliases[$name];
}
private function inspectApplication()
{
$this->commands = array();
$this->namespaces = array();
$all = $this->application->all($this->namespace ? $this->application->findNamespace($this->namespace) : null);
foreach ($this->sortCommands($all) as $namespace => $commands) {
$names = array();
foreach ($commands as $name => $command) {
if (!$command->getName()) {
continue;
}
if ($command->getName() === $name) {
$this->commands[$name] = $command;
} else {
$this->aliases[$name] = $command;
}
$names[] = $name;
}
$this->namespaces[$namespace] = array('id'=> $namespace,'commands'=> $names);
}
}
private function sortCommands(array $commands)
{
$namespacedCommands = array();
foreach ($commands as $name => $command) {
$key = $this->application->extractNamespace($name, 1);
if (!$key) {
$key ='_global';
}
$namespacedCommands[$key][$name] = $command;
}
ksort($namespacedCommands);
foreach ($namespacedCommands as &$commands) {
ksort($commands);
}
return $namespacedCommands;
}
}
}
namespace Symfony\Component\Console\Descriptor
{
interface DescriptorInterface
{
public function describe($object, array $options = array());
}
}
namespace Symfony\Component\Console\Descriptor
{
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
abstract class Descriptor implements DescriptorInterface
{
public function describe($object, array $options = array())
{
switch (true) {
case $object instanceof InputArgument:
return $this->describeInputArgument($object, $options);
case $object instanceof InputOption:
return $this->describeInputOption($object, $options);
case $object instanceof InputDefinition:
return $this->describeInputDefinition($object, $options);
case $object instanceof Command:
return $this->describeCommand($object, $options);
case $object instanceof Application:
return $this->describeApplication($object, $options);
}
throw new \InvalidArgumentException(sprintf('Object of type "%s" is not describable.', get_class($object)));
}
abstract protected function describeInputArgument(InputArgument $argument, array $options = array());
abstract protected function describeInputOption(InputOption $option, array $options = array());
abstract protected function describeInputDefinition(InputDefinition $definition, array $options = array());
abstract protected function describeCommand(Command $command, array $options = array());
abstract protected function describeApplication(Application $application, array $options = array());
}
}
namespace Symfony\Component\Console\Descriptor
{
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
class JsonDescriptor extends Descriptor
{
protected function describeInputArgument(InputArgument $argument, array $options = array())
{
return $this->output(array('name'=> $argument->getName(),'is_required'=> $argument->isRequired(),'is_array'=> $argument->isArray(),'description'=> $argument->getDescription(),'default'=> $argument->getDefault(),
), $options);
}
protected function describeInputOption(InputOption $option, array $options = array())
{
return $this->output(array('name'=>'--'.$option->getName(),'shortcut'=> $option->getShortcut() ?'-'.implode('|-', explode('|', $option->getShortcut())) :'','accept_value'=> $option->acceptValue(),'is_value_required'=> $option->isValueRequired(),'is_multiple'=> $option->isArray(),'description'=> $option->getDescription(),'default'=> $option->getDefault(),
), $options);
}
protected function describeInputDefinition(InputDefinition $definition, array $options = array())
{
$inputArguments = array();
foreach ($definition->getArguments() as $name => $argument) {
$inputArguments[$name] = $this->describeInputArgument($argument, array('as_array'=> true));
}
$inputOptions = array();
foreach ($definition->getOptions() as $name => $option) {
$inputOptions[$name] = $this->describeInputOption($option, array('as_array'=> true));
}
return $this->output(array('arguments'=> $inputArguments,'options'=> $inputOptions), $options);
}
protected function describeCommand(Command $command, array $options = array())
{
$command->getSynopsis();
$command->mergeApplicationDefinition(false);
return $this->output(array('name'=> $command->getName(),'usage'=> $command->getSynopsis(),'description'=> $command->getDescription(),'help'=> $command->getProcessedHelp(),'aliases'=> $command->getAliases(),'definition'=> $this->describeInputDefinition($command->getNativeDefinition(), array('as_array'=> true)),
), $options);
}
protected function describeApplication(Application $application, array $options = array())
{
$describedNamespace = isset($options['namespace']) ? $options['namespace'] : null;
$description = new ApplicationDescription($application, $describedNamespace);
$commands = array();
foreach ($description->getCommands() as $command) {
$commands[] = $this->describeCommand($command, array('as_array'=> true));
}
$data = $describedNamespace
? array('commands'=> $commands,'namespace'=> $describedNamespace)
: array('commands'=> $commands,'namespaces'=> array_values($description->getNamespaces()));
return $this->output($data, $options);
}
private function output(array $data, array $options)
{
if (isset($options['as_array']) && $options['as_array']) {
return $data;
}
return json_encode($data, isset($options['json_encoding']) ? $options['json_encoding'] : 0);
}
}
}
namespace Symfony\Component\Console\Descriptor
{
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
class MarkdownDescriptor extends Descriptor
{
protected function describeInputArgument(InputArgument $argument, array $options = array())
{
return'**'.$argument->getName().':**'."\n\n".'* Name: '.($argument->getName() ?:'<none>')."\n".'* Is required: '.($argument->isRequired() ?'yes':'no')."\n".'* Is array: '.($argument->isArray() ?'yes':'no')."\n".'* Description: '.($argument->getDescription() ?:'<none>')."\n".'* Default: `'.str_replace("\n",'', var_export($argument->getDefault(), true)).'`';
}
protected function describeInputOption(InputOption $option, array $options = array())
{
return'**'.$option->getName().':**'."\n\n".'* Name: `--'.$option->getName().'`'."\n".'* Shortcut: '.($option->getShortcut() ?'`-'.implode('|-', explode('|', $option->getShortcut())).'`':'<none>')."\n".'* Accept value: '.($option->acceptValue() ?'yes':'no')."\n".'* Is value required: '.($option->isValueRequired() ?'yes':'no')."\n".'* Is multiple: '.($option->isArray() ?'yes':'no')."\n".'* Description: '.($option->getDescription() ?:'<none>')."\n".'* Default: `'.str_replace("\n",'', var_export($option->getDefault(), true)).'`';
}
protected function describeInputDefinition(InputDefinition $definition, array $options = array())
{
$blocks = array();
if (count($definition->getArguments()) > 0) {
$blocks[] ='### Arguments:';
foreach ($definition->getArguments() as $argument) {
$blocks[] = $this->describeInputArgument($argument);
}
}
if (count($definition->getOptions()) > 0) {
$blocks[] ='### Options:';
foreach ($definition->getOptions() as $option) {
$blocks[] = $this->describeInputOption($option);
}
}
return implode("\n\n", $blocks);
}
protected function describeCommand(Command $command, array $options = array())
{
$command->getSynopsis();
$command->mergeApplicationDefinition(false);
$markdown = $command->getName()."\n".str_repeat('-', strlen($command->getName()))."\n\n".'* Description: '.($command->getDescription() ?:'<none>')."\n".'* Usage: `'.$command->getSynopsis().'`'."\n".'* Aliases: '.(count($command->getAliases()) ?'`'.implode('`, `', $command->getAliases()).'`':'<none>');
if ($help = $command->getProcessedHelp()) {
$markdown .="\n\n".$help;
}
if ($definitionMarkdown = $this->describeInputDefinition($command->getNativeDefinition())) {
$markdown .="\n\n".$definitionMarkdown;
}
return $markdown;
}
protected function describeApplication(Application $application, array $options = array())
{
$describedNamespace = isset($options['namespace']) ? $options['namespace'] : null;
$description = new ApplicationDescription($application, $describedNamespace);
$blocks = array($application->getName()."\n".str_repeat('=', strlen($application->getName())));
foreach ($description->getNamespaces() as $namespace) {
if (ApplicationDescription::GLOBAL_NAMESPACE !== $namespace['id']) {
$blocks[] ='**'.$namespace['id'].':**';
}
$blocks[] = implode("\n", array_map(function ($commandName) {
return'* '.$commandName;
} , $namespace['commands']));
}
foreach ($description->getCommands() as $command) {
$blocks[] = $this->describeCommand($command);
}
return implode("\n\n", $blocks);
}
}
}
namespace Symfony\Component\Console\Descriptor
{
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
class TextDescriptor extends Descriptor
{
protected function describeInputArgument(InputArgument $argument, array $options = array())
{
if (null !== $argument->getDefault() && (!is_array($argument->getDefault()) || count($argument->getDefault()))) {
$default = sprintf('<comment> (default: %s)</comment>', $this->formatDefaultValue($argument->getDefault()));
} else {
$default ='';
}
$nameWidth = isset($options['name_width']) ? $options['name_width'] : strlen($argument->getName());
$output = str_replace("\n","\n".str_repeat(' ', $nameWidth + 2), $argument->getDescription());
$output = sprintf(" <info>%-${nameWidth}s</info> %s%s", $argument->getName(), $output, $default);
return isset($options['raw_text']) && $options['raw_text'] ? strip_tags($output) : $output;
}
protected function describeInputOption(InputOption $option, array $options = array())
{
if ($option->acceptValue() && null !== $option->getDefault() && (!is_array($option->getDefault()) || count($option->getDefault()))) {
$default = sprintf('<comment> (default: %s)</comment>', $this->formatDefaultValue($option->getDefault()));
} else {
$default ='';
}
$nameWidth = isset($options['name_width']) ? $options['name_width'] : strlen($option->getName());
$nameWithShortcutWidth = $nameWidth - strlen($option->getName()) - 2;
$output = sprintf(" <info>%s</info> %-${nameWithShortcutWidth}s%s%s%s",'--'.$option->getName(),
$option->getShortcut() ? sprintf('(-%s) ', $option->getShortcut()) :'',
str_replace("\n","\n".str_repeat(' ', $nameWidth + 2), $option->getDescription()),
$default,
$option->isArray() ?'<comment> (multiple values allowed)</comment>':'');
return isset($options['raw_text']) && $options['raw_text'] ? strip_tags($output) : $output;
}
protected function describeInputDefinition(InputDefinition $definition, array $options = array())
{
$nameWidth = 0;
foreach ($definition->getOptions() as $option) {
$nameLength = strlen($option->getName()) + 2;
if ($option->getShortcut()) {
$nameLength += strlen($option->getShortcut()) + 3;
}
$nameWidth = max($nameWidth, $nameLength);
}
foreach ($definition->getArguments() as $argument) {
$nameWidth = max($nameWidth, strlen($argument->getName()));
}
++$nameWidth;
$messages = array();
if ($definition->getArguments()) {
$messages[] ='<comment>Arguments:</comment>';
foreach ($definition->getArguments() as $argument) {
$messages[] = $this->describeInputArgument($argument, array('name_width'=> $nameWidth));
}
$messages[] ='';
}
if ($definition->getOptions()) {
$messages[] ='<comment>Options:</comment>';
foreach ($definition->getOptions() as $option) {
$messages[] = $this->describeInputOption($option, array('name_width'=> $nameWidth));
}
$messages[] ='';
}
$output = implode("\n", $messages);
return isset($options['raw_text']) && $options['raw_text'] ? strip_tags($output) : $output;
}
protected function describeCommand(Command $command, array $options = array())
{
$command->getSynopsis();
$command->mergeApplicationDefinition(false);
$messages = array('<comment>Usage:</comment>',' '.$command->getSynopsis(),'');
if ($command->getAliases()) {
$messages[] ='<comment>Aliases:</comment> <info>'.implode(', ', $command->getAliases()).'</info>';
}
$messages[] = $this->describeInputDefinition($command->getNativeDefinition());
if ($help = $command->getProcessedHelp()) {
$messages[] ='<comment>Help:</comment>';
$messages[] =' '.str_replace("\n","\n ", $help)."\n";
}
$output = implode("\n", $messages);
return isset($options['raw_text']) && $options['raw_text'] ? strip_tags($output) : $output;
}
protected function describeApplication(Application $application, array $options = array())
{
$describedNamespace = isset($options['namespace']) ? $options['namespace'] : null;
$description = new ApplicationDescription($application, $describedNamespace);
$messages = array();
if (isset($options['raw_text']) && $options['raw_text']) {
$width = $this->getColumnWidth($description->getCommands());
foreach ($description->getCommands() as $command) {
$messages[] = sprintf("%-${width}s %s", $command->getName(), $command->getDescription());
}
} else {
$width = $this->getColumnWidth($description->getCommands());
$messages[] = $application->getHelp();
$messages[] ='';
if ($describedNamespace) {
$messages[] = sprintf("<comment>Available commands for the \"%s\" namespace:</comment>", $describedNamespace);
} else {
$messages[] ='<comment>Available commands:</comment>';
}
foreach ($description->getNamespaces() as $namespace) {
if (!$describedNamespace && ApplicationDescription::GLOBAL_NAMESPACE !== $namespace['id']) {
$messages[] ='<comment>'.$namespace['id'].'</comment>';
}
foreach ($namespace['commands'] as $name) {
$messages[] = sprintf(" <info>%-${width}s</info> %s", $name, $description->getCommand($name)->getDescription());
}
}
}
$output = implode("\n", $messages);
return isset($options['raw_text']) && $options['raw_text'] ? strip_tags($output) : $output;
}
private function formatDefaultValue($default)
{
if (version_compare(PHP_VERSION,'5.4','<')) {
return str_replace('\/','/', json_encode($default));
}
return json_encode($default, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
private function getColumnWidth(array $commands)
{
$width = 0;
foreach ($commands as $command) {
$width = strlen($command->getName()) > $width ? strlen($command->getName()) : $width;
}
return $width + 2;
}
}
}
namespace Symfony\Component\Console\Descriptor
{
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
class XmlDescriptor extends Descriptor
{
protected function describeInputArgument(InputArgument $argument, array $options = array())
{
$dom = new \DOMDocument('1.0','UTF-8');
$dom->appendChild($objectXML = $dom->createElement('argument'));
$objectXML->setAttribute('name', $argument->getName());
$objectXML->setAttribute('is_required', $argument->isRequired() ? 1 : 0);
$objectXML->setAttribute('is_array', $argument->isArray() ? 1 : 0);
$objectXML->appendChild($descriptionXML = $dom->createElement('description'));
$descriptionXML->appendChild($dom->createTextNode($argument->getDescription()));
$objectXML->appendChild($defaultsXML = $dom->createElement('defaults'));
$defaults = is_array($argument->getDefault()) ? $argument->getDefault() : (is_bool($argument->getDefault()) ? array(var_export($argument->getDefault(), true)) : ($argument->getDefault() ? array($argument->getDefault()) : array()));
foreach ($defaults as $default) {
$defaultsXML->appendChild($defaultXML = $dom->createElement('default'));
$defaultXML->appendChild($dom->createTextNode($default));
}
return $this->output($dom, $options);
}
protected function describeInputOption(InputOption $option, array $options = array())
{
$dom = new \DOMDocument('1.0','UTF-8');
$dom->appendChild($objectXML = $dom->createElement('option'));
$objectXML->setAttribute('name','--'.$option->getName());
$pos = strpos($option->getShortcut(),'|');
if (false !== $pos) {
$objectXML->setAttribute('shortcut','-'.substr($option->getShortcut(), 0, $pos));
$objectXML->setAttribute('shortcuts','-'.implode('|-', explode('|', $option->getShortcut())));
} else {
$objectXML->setAttribute('shortcut', $option->getShortcut() ?'-'.$option->getShortcut() :'');
}
$objectXML->setAttribute('accept_value', $option->acceptValue() ? 1 : 0);
$objectXML->setAttribute('is_value_required', $option->isValueRequired() ? 1 : 0);
$objectXML->setAttribute('is_multiple', $option->isArray() ? 1 : 0);
$objectXML->appendChild($descriptionXML = $dom->createElement('description'));
$descriptionXML->appendChild($dom->createTextNode($option->getDescription()));
if ($option->acceptValue()) {
$defaults = is_array($option->getDefault()) ? $option->getDefault() : (is_bool($option->getDefault()) ? array(var_export($option->getDefault(), true)) : ($option->getDefault() ? array($option->getDefault()) : array()));
$objectXML->appendChild($defaultsXML = $dom->createElement('defaults'));
if (!empty($defaults)) {
foreach ($defaults as $default) {
$defaultsXML->appendChild($defaultXML = $dom->createElement('default'));
$defaultXML->appendChild($dom->createTextNode($default));
}
}
}
return $this->output($dom, $options);
}
protected function describeInputDefinition(InputDefinition $definition, array $options = array())
{
$dom = new \DOMDocument('1.0','UTF-8');
$dom->appendChild($definitionXML = $dom->createElement('definition'));
$definitionXML->appendChild($argumentsXML = $dom->createElement('arguments'));
foreach ($definition->getArguments() as $argument) {
$this->appendDocument($argumentsXML, $this->describeInputArgument($argument, array('as_dom'=> true)));
}
$definitionXML->appendChild($optionsXML = $dom->createElement('options'));
foreach ($definition->getOptions() as $option) {
$this->appendDocument($optionsXML, $this->describeInputOption($option, array('as_dom'=> true)));
}
return $this->output($dom, $options);
}
protected function describeCommand(Command $command, array $options = array())
{
$dom = new \DOMDocument('1.0','UTF-8');
$dom->appendChild($commandXML = $dom->createElement('command'));
$command->getSynopsis();
$command->mergeApplicationDefinition(false);
$commandXML->setAttribute('id', $command->getName());
$commandXML->setAttribute('name', $command->getName());
$commandXML->appendChild($usageXML = $dom->createElement('usage'));
$usageXML->appendChild($dom->createTextNode(sprintf($command->getSynopsis(),'')));
$commandXML->appendChild($descriptionXML = $dom->createElement('description'));
$descriptionXML->appendChild($dom->createTextNode(str_replace("\n","\n ", $command->getDescription())));
$commandXML->appendChild($helpXML = $dom->createElement('help'));
$helpXML->appendChild($dom->createTextNode(str_replace("\n","\n ", $command->getProcessedHelp())));
$commandXML->appendChild($aliasesXML = $dom->createElement('aliases'));
foreach ($command->getAliases() as $alias) {
$aliasesXML->appendChild($aliasXML = $dom->createElement('alias'));
$aliasXML->appendChild($dom->createTextNode($alias));
}
$definitionXML = $this->describeInputDefinition($command->getNativeDefinition(), array('as_dom'=> true));
$this->appendDocument($commandXML, $definitionXML->getElementsByTagName('definition')->item(0));
return $this->output($dom, $options);
}
protected function describeApplication(Application $application, array $options = array())
{
$dom = new \DOMDocument('1.0','UTF-8');
$dom->appendChild($rootXml = $dom->createElement('symfony'));
$rootXml->appendChild($commandsXML = $dom->createElement('commands'));
$describedNamespace = isset($options['namespace']) ? $options['namespace'] : null;
$description = new ApplicationDescription($application, $describedNamespace);
if ($describedNamespace) {
$commandsXML->setAttribute('namespace', $describedNamespace);
}
foreach ($description->getCommands() as $command) {
$this->appendDocument($commandsXML, $this->describeCommand($command, array('as_dom'=> true)));
}
if (!$describedNamespace) {
$rootXml->appendChild($namespacesXML = $dom->createElement('namespaces'));
foreach ($description->getNamespaces() as $namespace) {
$namespacesXML->appendChild($namespaceArrayXML = $dom->createElement('namespace'));
$namespaceArrayXML->setAttribute('id', $namespace['id']);
foreach ($namespace['commands'] as $name) {
$namespaceArrayXML->appendChild($commandXML = $dom->createElement('command'));
$commandXML->appendChild($dom->createTextNode($name));
}
}
}
return $this->output($dom, $options);
}
private function appendDocument(\DOMNode $parentNode, \DOMNode $importedParent)
{
foreach ($importedParent->childNodes as $childNode) {
$parentNode->appendChild($parentNode->ownerDocument->importNode($childNode, true));
}
}
private function output(\DOMDocument $dom, array $options)
{
if (isset($options['as_dom']) && $options['as_dom']) {
return $dom;
}
$dom->formatOutput = true;
return $dom->saveXML();
}
}
}
namespace Symfony\Component\Console\Formatter
{
interface OutputFormatterInterface
{
public function setDecorated($decorated);
public function isDecorated();
public function setStyle($name, OutputFormatterStyleInterface $style);
public function hasStyle($name);
public function getStyle($name);
public function format($message);
}
}
namespace Symfony\Component\Console\Formatter
{
class OutputFormatter implements OutputFormatterInterface
{
const FORMAT_PATTERN ='#(\\\\?)<(/?)([a-z][a-z0-9_=;-]+)?>((?: [^<\\\\]+ | (?!<(?:/?[a-z]|/>)). | .(?<=\\\\<) )*)#isx';
private $decorated;
private $styles = array();
private $styleStack;
public static function escape($text)
{
return preg_replace('/([^\\\\]?)</is','$1\\<', $text);
}
public function __construct($decorated = null, array $styles = array())
{
$this->decorated = (Boolean) $decorated;
$this->setStyle('error', new OutputFormatterStyle('white','red'));
$this->setStyle('info', new OutputFormatterStyle('green'));
$this->setStyle('comment', new OutputFormatterStyle('yellow'));
$this->setStyle('question', new OutputFormatterStyle('black','cyan'));
foreach ($styles as $name => $style) {
$this->setStyle($name, $style);
}
$this->styleStack = new OutputFormatterStyleStack();
}
public function setDecorated($decorated)
{
$this->decorated = (Boolean) $decorated;
}
public function isDecorated()
{
return $this->decorated;
}
public function setStyle($name, OutputFormatterStyleInterface $style)
{
$this->styles[strtolower($name)] = $style;
}
public function hasStyle($name)
{
return isset($this->styles[strtolower($name)]);
}
public function getStyle($name)
{
if (!$this->hasStyle($name)) {
throw new \InvalidArgumentException(sprintf('Undefined style: %s', $name));
}
return $this->styles[strtolower($name)];
}
public function format($message)
{
$message = preg_replace_callback(self::FORMAT_PATTERN, array($this,'replaceStyle'), $message);
return str_replace('\\<','<', $message);
}
public function getStyleStack()
{
return $this->styleStack;
}
private function replaceStyle($match)
{
if ('\\'=== $match[1]) {
return $this->applyCurrentStyle($match[0]);
}
if (''=== $match[3]) {
if ('/'=== $match[2]) {
$this->styleStack->pop();
return $this->applyCurrentStyle($match[4]);
}
return'<>'.$this->applyCurrentStyle($match[4]);
}
if (isset($this->styles[strtolower($match[3])])) {
$style = $this->styles[strtolower($match[3])];
} else {
$style = $this->createStyleFromString($match[3]);
if (false === $style) {
return $this->applyCurrentStyle($match[0]);
}
}
if ('/'=== $match[2]) {
$this->styleStack->pop($style);
} else {
$this->styleStack->push($style);
}
return $this->applyCurrentStyle($match[4]);
}
private function createStyleFromString($string)
{
if (!preg_match_all('/([^=]+)=([^;]+)(;|$)/', strtolower($string), $matches, PREG_SET_ORDER)) {
return false;
}
$style = new OutputFormatterStyle();
foreach ($matches as $match) {
array_shift($match);
if ('fg'== $match[0]) {
$style->setForeground($match[1]);
} elseif ('bg'== $match[0]) {
$style->setBackground($match[1]);
} else {
$style->setOption($match[1]);
}
}
return $style;
}
private function applyCurrentStyle($text)
{
return $this->isDecorated() && strlen($text) > 0 ? $this->styleStack->getCurrent()->apply($text) : $text;
}
}
}
namespace Symfony\Component\Console\Formatter
{
interface OutputFormatterStyleInterface
{
public function setForeground($color = null);
public function setBackground($color = null);
public function setOption($option);
public function unsetOption($option);
public function setOptions(array $options);
public function apply($text);
}
}
namespace Symfony\Component\Console\Formatter
{
class OutputFormatterStyle implements OutputFormatterStyleInterface
{
private static $availableForegroundColors = array('black'=> 30,'red'=> 31,'green'=> 32,'yellow'=> 33,'blue'=> 34,'magenta'=> 35,'cyan'=> 36,'white'=> 37
);
private static $availableBackgroundColors = array('black'=> 40,'red'=> 41,'green'=> 42,'yellow'=> 43,'blue'=> 44,'magenta'=> 45,'cyan'=> 46,'white'=> 47
);
private static $availableOptions = array('bold'=> 1,'underscore'=> 4,'blink'=> 5,'reverse'=> 7,'conceal'=> 8
);
private $foreground;
private $background;
private $options = array();
public function __construct($foreground = null, $background = null, array $options = array())
{
if (null !== $foreground) {
$this->setForeground($foreground);
}
if (null !== $background) {
$this->setBackground($background);
}
if (count($options)) {
$this->setOptions($options);
}
}
public function setForeground($color = null)
{
if (null === $color) {
$this->foreground = null;
return;
}
if (!isset(static::$availableForegroundColors[$color])) {
throw new \InvalidArgumentException(sprintf('Invalid foreground color specified: "%s". Expected one of (%s)',
$color,
implode(', ', array_keys(static::$availableForegroundColors))
));
}
$this->foreground = static::$availableForegroundColors[$color];
}
public function setBackground($color = null)
{
if (null === $color) {
$this->background = null;
return;
}
if (!isset(static::$availableBackgroundColors[$color])) {
throw new \InvalidArgumentException(sprintf('Invalid background color specified: "%s". Expected one of (%s)',
$color,
implode(', ', array_keys(static::$availableBackgroundColors))
));
}
$this->background = static::$availableBackgroundColors[$color];
}
public function setOption($option)
{
if (!isset(static::$availableOptions[$option])) {
throw new \InvalidArgumentException(sprintf('Invalid option specified: "%s". Expected one of (%s)',
$option,
implode(', ', array_keys(static::$availableOptions))
));
}
if (false === array_search(static::$availableOptions[$option], $this->options)) {
$this->options[] = static::$availableOptions[$option];
}
}
public function unsetOption($option)
{
if (!isset(static::$availableOptions[$option])) {
throw new \InvalidArgumentException(sprintf('Invalid option specified: "%s". Expected one of (%s)',
$option,
implode(', ', array_keys(static::$availableOptions))
));
}
$pos = array_search(static::$availableOptions[$option], $this->options);
if (false !== $pos) {
unset($this->options[$pos]);
}
}
public function setOptions(array $options)
{
$this->options = array();
foreach ($options as $option) {
$this->setOption($option);
}
}
public function apply($text)
{
$codes = array();
if (null !== $this->foreground) {
$codes[] = $this->foreground;
}
if (null !== $this->background) {
$codes[] = $this->background;
}
if (count($this->options)) {
$codes = array_merge($codes, $this->options);
}
if (0 === count($codes)) {
return $text;
}
return sprintf("\033[%sm%s\033[0m", implode(';', $codes), $text);
}
}
}
namespace Symfony\Component\Console\Formatter
{
class OutputFormatterStyleStack
{
private $styles;
private $emptyStyle;
public function __construct(OutputFormatterStyleInterface $emptyStyle = null)
{
$this->emptyStyle = $emptyStyle ?: new OutputFormatterStyle();
$this->reset();
}
public function reset()
{
$this->styles = array();
}
public function push(OutputFormatterStyleInterface $style)
{
$this->styles[] = $style;
}
public function pop(OutputFormatterStyleInterface $style = null)
{
if (empty($this->styles)) {
return $this->emptyStyle;
}
if (null === $style) {
return array_pop($this->styles);
}
foreach (array_reverse($this->styles, true) as $index => $stackedStyle) {
if ($style->apply('') === $stackedStyle->apply('')) {
$this->styles = array_slice($this->styles, 0, $index);
return $stackedStyle;
}
}
throw new \InvalidArgumentException('Incorrectly nested style tag found.');
}
public function getCurrent()
{
if (empty($this->styles)) {
return $this->emptyStyle;
}
return $this->styles[count($this->styles)-1];
}
public function setEmptyStyle(OutputFormatterStyleInterface $emptyStyle)
{
$this->emptyStyle = $emptyStyle;
return $this;
}
public function getEmptyStyle()
{
return $this->emptyStyle;
}
}
}
namespace Symfony\Component\Console\Helper
{
interface HelperInterface
{
public function setHelperSet(HelperSet $helperSet = null);
public function getHelperSet();
public function getName();
}
}
namespace Symfony\Component\Console\Helper
{
abstract class Helper implements HelperInterface
{
protected $helperSet = null;
public function setHelperSet(HelperSet $helperSet = null)
{
$this->helperSet = $helperSet;
}
public function getHelperSet()
{
return $this->helperSet;
}
protected function strlen($string)
{
if (!function_exists('mb_strlen')) {
return strlen($string);
}
if (false === $encoding = mb_detect_encoding($string)) {
return strlen($string);
}
return mb_strlen($string, $encoding);
}
}
}
namespace Symfony\Component\Console\Helper
{
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Descriptor\DescriptorInterface;
use Symfony\Component\Console\Descriptor\JsonDescriptor;
use Symfony\Component\Console\Descriptor\MarkdownDescriptor;
use Symfony\Component\Console\Descriptor\TextDescriptor;
use Symfony\Component\Console\Descriptor\XmlDescriptor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
class DescriptorHelper extends Helper
{
private $descriptors = array();
public function __construct()
{
$this
->register('txt', new TextDescriptor())
->register('xml', new XmlDescriptor())
->register('json', new JsonDescriptor())
->register('md', new MarkdownDescriptor())
;
}
public function describe(OutputInterface $output, $object, $format = null, $raw = false, $namespace = null)
{
$options = array('raw_text'=> $raw,'format'=> $format ?:'txt','namespace'=> $namespace);
$type = !$raw &&'txt'=== $options['format'] ? OutputInterface::OUTPUT_NORMAL : OutputInterface::OUTPUT_RAW;
if (!isset($this->descriptors[$options['format']])) {
throw new \InvalidArgumentException(sprintf('Unsupported format "%s".', $options['format']));
}
$descriptor = $this->descriptors[$options['format']];
$output->writeln($descriptor->describe($object, $options), $type);
}
public function register($format, DescriptorInterface $descriptor)
{
$this->descriptors[$format] = $descriptor;
return $this;
}
public function getName()
{
return'descriptor';
}
}
}
namespace Symfony\Component\Console\Helper
{
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
class DialogHelper extends Helper
{
private $inputStream;
private static $shell;
private static $stty;
public function select(OutputInterface $output, $question, $choices, $default = null, $attempts = false, $errorMessage ='Value "%s" is invalid', $multiselect = false)
{
$width = max(array_map('strlen', array_keys($choices)));
$messages = (array) $question;
foreach ($choices as $key => $value) {
$messages[] = sprintf(" [<info>%-${width}s</info>] %s", $key, $value);
}
$output->writeln($messages);
$result = $this->askAndValidate($output,'> ', function ($picked) use ($choices, $errorMessage, $multiselect) {
$selectedChoices = str_replace(" ","", $picked);
if ($multiselect) {
if(!preg_match('/^[a-zA-Z0-9_-]+(?:,[a-zA-Z0-9_-]+)*$/', $selectedChoices, $matches)) {
throw new \InvalidArgumentException(sprintf($errorMessage, $picked));
}
$selectedChoices = explode(",", $selectedChoices);
} else {
$selectedChoices = array($picked);
}
$multiselectChoices = array();
foreach ($selectedChoices as $value) {
if (empty($choices[$value])) {
throw new \InvalidArgumentException(sprintf($errorMessage, $value));
}
array_push($multiselectChoices, $value);
}
if ($multiselect){
return $multiselectChoices;
}
return $picked;
}, $attempts, $default);
return $result;
}
public function ask(OutputInterface $output, $question, $default = null, array $autocomplete = null)
{
$output->write($question);
$inputStream = $this->inputStream ?: STDIN;
if (null === $autocomplete || !$this->hasSttyAvailable()) {
$ret = fgets($inputStream, 4096);
if (false === $ret) {
throw new \RuntimeException('Aborted');
}
$ret = trim($ret);
} else {
$ret ='';
$i = 0;
$ofs = -1;
$matches = $autocomplete;
$numMatches = count($matches);
$sttyMode = shell_exec('stty -g');
shell_exec('stty -icanon -echo');
$output->getFormatter()->setStyle('hl', new OutputFormatterStyle('black','white'));
while (!feof($inputStream)) {
$c = fread($inputStream, 1);
if ("\177"=== $c) {
if (0 === $numMatches && 0 !== $i) {
$i--;
$output->write("\033[1D");
}
if ($i === 0) {
$ofs = -1;
$matches = $autocomplete;
$numMatches = count($matches);
} else {
$numMatches = 0;
}
$ret = substr($ret, 0, $i);
} elseif ("\033"=== $c) { $c .= fread($inputStream, 2);
if ('A'=== $c[2] ||'B'=== $c[2]) {
if ('A'=== $c[2] && -1 === $ofs) {
$ofs = 0;
}
if (0 === $numMatches) {
continue;
}
$ofs += ('A'=== $c[2]) ? -1 : 1;
$ofs = ($numMatches + $ofs) % $numMatches;
}
} elseif (ord($c) < 32) {
if ("\t"=== $c ||"\n"=== $c) {
if ($numMatches > 0 && -1 !== $ofs) {
$ret = $matches[$ofs];
$output->write(substr($ret, $i));
$i = strlen($ret);
}
if ("\n"=== $c) {
$output->write($c);
break;
}
$numMatches = 0;
}
continue;
} else {
$output->write($c);
$ret .= $c;
$i++;
$numMatches = 0;
$ofs = 0;
foreach ($autocomplete as $value) {
if (0 === strpos($value, $ret) && $i !== strlen($value)) {
$matches[$numMatches++] = $value;
}
}
}
$output->write("\033[K");
if ($numMatches > 0 && -1 !== $ofs) {
$output->write("\0337");
$output->write('<hl>'.substr($matches[$ofs], $i).'</hl>');
$output->write("\0338");
}
}
shell_exec(sprintf('stty %s', $sttyMode));
}
return strlen($ret) > 0 ? $ret : $default;
}
public function askConfirmation(OutputInterface $output, $question, $default = true)
{
$answer ='z';
while ($answer && !in_array(strtolower($answer[0]), array('y','n'))) {
$answer = $this->ask($output, $question);
}
if (false === $default) {
return $answer &&'y'== strtolower($answer[0]);
}
return !$answer ||'y'== strtolower($answer[0]);
}
public function askHiddenResponse(OutputInterface $output, $question, $fallback = true)
{
if (defined('PHP_WINDOWS_VERSION_BUILD')) {
$exe = __DIR__.'/../Resources/bin/hiddeninput.exe';
if ('phar:'=== substr(__FILE__, 0, 5)) {
$tmpExe = sys_get_temp_dir().'/hiddeninput.exe';
copy($exe, $tmpExe);
$exe = $tmpExe;
}
$output->write($question);
$value = rtrim(shell_exec($exe));
$output->writeln('');
if (isset($tmpExe)) {
unlink($tmpExe);
}
return $value;
}
if ($this->hasSttyAvailable()) {
$output->write($question);
$sttyMode = shell_exec('stty -g');
shell_exec('stty -echo');
$value = fgets($this->inputStream ?: STDIN, 4096);
shell_exec(sprintf('stty %s', $sttyMode));
if (false === $value) {
throw new \RuntimeException('Aborted');
}
$value = trim($value);
$output->writeln('');
return $value;
}
if (false !== $shell = $this->getShell()) {
$output->write($question);
$readCmd = $shell ==='csh'?'set mypassword = $<':'read -r mypassword';
$command = sprintf("/usr/bin/env %s -c 'stty -echo; %s; stty echo; echo \$mypassword'", $shell, $readCmd);
$value = rtrim(shell_exec($command));
$output->writeln('');
return $value;
}
if ($fallback) {
return $this->ask($output, $question);
}
throw new \RuntimeException('Unable to hide the response');
}
public function askAndValidate(OutputInterface $output, $question, $validator, $attempts = false, $default = null, array $autocomplete = null)
{
$that = $this;
$interviewer = function() use ($output, $question, $default, $autocomplete, $that) {
return $that->ask($output, $question, $default, $autocomplete);
};
return $this->validateAttempts($interviewer, $output, $validator, $attempts);
}
public function askHiddenResponseAndValidate(OutputInterface $output, $question, $validator, $attempts = false, $fallback = true)
{
$that = $this;
$interviewer = function() use ($output, $question, $fallback, $that) {
return $that->askHiddenResponse($output, $question, $fallback);
};
return $this->validateAttempts($interviewer, $output, $validator, $attempts);
}
public function setInputStream($stream)
{
$this->inputStream = $stream;
}
public function getInputStream()
{
return $this->inputStream;
}
public function getName()
{
return'dialog';
}
private function getShell()
{
if (null !== self::$shell) {
return self::$shell;
}
self::$shell = false;
if (file_exists('/usr/bin/env')) {
$test ="/usr/bin/env %s -c 'echo OK' 2> /dev/null";
foreach (array('bash','zsh','ksh','csh') as $sh) {
if ('OK'=== rtrim(shell_exec(sprintf($test, $sh)))) {
self::$shell = $sh;
break;
}
}
}
return self::$shell;
}
private function hasSttyAvailable()
{
if (null !== self::$stty) {
return self::$stty;
}
exec('stty 2>&1', $output, $exitcode);
return self::$stty = $exitcode === 0;
}
private function validateAttempts($interviewer, OutputInterface $output, $validator, $attempts)
{
$error = null;
while (false === $attempts || $attempts--) {
if (null !== $error) {
$output->writeln($this->getHelperSet()->get('formatter')->formatBlock($error->getMessage(),'error'));
}
try {
return call_user_func($validator, $interviewer());
} catch (\Exception $error) {
}
}
throw $error;
}
}
}
namespace Symfony\Component\Console\Helper
{
use Symfony\Component\Console\Formatter\OutputFormatter;
class FormatterHelper extends Helper
{
public function formatSection($section, $message, $style ='info')
{
return sprintf('<%s>[%s]</%s> %s', $style, $section, $style, $message);
}
public function formatBlock($messages, $style, $large = false)
{
$messages = (array) $messages;
$len = 0;
$lines = array();
foreach ($messages as $message) {
$message = OutputFormatter::escape($message);
$lines[] = sprintf($large ?'  %s  ':' %s ', $message);
$len = max($this->strlen($message) + ($large ? 4 : 2), $len);
}
$messages = $large ? array(str_repeat(' ', $len)) : array();
foreach ($lines as $line) {
$messages[] = $line.str_repeat(' ', $len - $this->strlen($line));
}
if ($large) {
$messages[] = str_repeat(' ', $len);
}
foreach ($messages as &$message) {
$message = sprintf('<%s>%s</%s>', $style, $message, $style);
}
return implode("\n", $messages);
}
public function getName()
{
return'formatter';
}
}
}
namespace Symfony\Component\Console\Helper
{
use Symfony\Component\Console\Command\Command;
class HelperSet
{
private $helpers;
private $command;
public function __construct(array $helpers = array())
{
$this->helpers = array();
foreach ($helpers as $alias => $helper) {
$this->set($helper, is_int($alias) ? null : $alias);
}
}
public function set(HelperInterface $helper, $alias = null)
{
$this->helpers[$helper->getName()] = $helper;
if (null !== $alias) {
$this->helpers[$alias] = $helper;
}
$helper->setHelperSet($this);
}
public function has($name)
{
return isset($this->helpers[$name]);
}
public function get($name)
{
if (!$this->has($name)) {
throw new \InvalidArgumentException(sprintf('The helper "%s" is not defined.', $name));
}
return $this->helpers[$name];
}
public function setCommand(Command $command = null)
{
$this->command = $command;
}
public function getCommand()
{
return $this->command;
}
}
}
namespace Symfony\Component\Console\Helper
{
use Symfony\Component\Console\Output\OutputInterface;
class ProgressHelper extends Helper
{
const FORMAT_QUIET =' %percent%%';
const FORMAT_NORMAL =' %current%/%max% [%bar%] %percent%%';
const FORMAT_VERBOSE =' %current%/%max% [%bar%] %percent%% Elapsed: %elapsed%';
const FORMAT_QUIET_NOMAX =' %current%';
const FORMAT_NORMAL_NOMAX =' %current% [%bar%]';
const FORMAT_VERBOSE_NOMAX =' %current% [%bar%] Elapsed: %elapsed%';
private $barWidth = 28;
private $barChar ='=';
private $emptyBarChar ='-';
private $progressChar ='>';
private $format = null;
private $redrawFreq = 1;
private $lastMessagesLength;
private $barCharOriginal;
private $output;
private $current;
private $max;
private $startTime;
private $defaultFormatVars = array('current','max','bar','percent','elapsed',
);
private $formatVars;
private $widths = array('current'=> 4,'max'=> 4,'percent'=> 3,'elapsed'=> 6,
);
private $timeFormats = array(
array(0,'???'),
array(2,'1 sec'),
array(59,'secs', 1),
array(60,'1 min'),
array(3600,'mins', 60),
array(5400,'1 hr'),
array(86400,'hrs', 3600),
array(129600,'1 day'),
array(604800,'days', 86400),
);
public function setBarWidth($size)
{
$this->barWidth = (int) $size;
}
public function setBarCharacter($char)
{
$this->barChar = $char;
}
public function setEmptyBarCharacter($char)
{
$this->emptyBarChar = $char;
}
public function setProgressCharacter($char)
{
$this->progressChar = $char;
}
public function setFormat($format)
{
$this->format = $format;
}
public function setRedrawFrequency($freq)
{
$this->redrawFreq = (int) $freq;
}
public function start(OutputInterface $output, $max = null)
{
$this->startTime = time();
$this->current = 0;
$this->max = (int) $max;
$this->output = $output;
if (null === $this->format) {
switch ($output->getVerbosity()) {
case OutputInterface::VERBOSITY_QUIET:
$this->format = self::FORMAT_QUIET_NOMAX;
if ($this->max > 0) {
$this->format = self::FORMAT_QUIET;
}
break;
case OutputInterface::VERBOSITY_VERBOSE:
case OutputInterface::VERBOSITY_VERY_VERBOSE:
case OutputInterface::VERBOSITY_DEBUG:
$this->format = self::FORMAT_VERBOSE_NOMAX;
if ($this->max > 0) {
$this->format = self::FORMAT_VERBOSE;
}
break;
default:
$this->format = self::FORMAT_NORMAL_NOMAX;
if ($this->max > 0) {
$this->format = self::FORMAT_NORMAL;
}
break;
}
}
$this->initialize();
}
public function advance($step = 1, $redraw = false)
{
if (null === $this->startTime) {
throw new \LogicException('You must start the progress bar before calling advance().');
}
if (0 === $this->current) {
$redraw = true;
}
$this->current += $step;
if ($redraw || 0 === $this->current % $this->redrawFreq) {
$this->display();
}
}
public function setCurrent($current, $redraw = false)
{
if (null === $this->startTime) {
throw new \LogicException('You must start the progress bar before calling setCurrent().');
}
$current = (int) $current;
if ($current < $this->current) {
throw new \LogicException('You can\'t regress the progress bar');
}
if (0 === $this->current) {
$redraw = true;
}
$this->current = $current;
if ($redraw || 0 === $this->current % $this->redrawFreq) {
$this->display();
}
}
public function display($finish = false)
{
if (null === $this->startTime) {
throw new \LogicException('You must start the progress bar before calling display().');
}
$message = $this->format;
foreach ($this->generate($finish) as $name => $value) {
$message = str_replace("%{$name}%", $value, $message);
}
$this->overwrite($this->output, $message);
}
public function finish()
{
if (null === $this->startTime) {
throw new \LogicException('You must start the progress bar before calling finish().');
}
if (null !== $this->startTime) {
if (!$this->max) {
$this->barChar = $this->barCharOriginal;
$this->display(true);
}
$this->startTime = null;
$this->output->writeln('');
$this->output = null;
}
}
private function initialize()
{
$this->formatVars = array();
foreach ($this->defaultFormatVars as $var) {
if (false !== strpos($this->format, "%{$var}%")) {
$this->formatVars[$var] = true;
}
}
if ($this->max > 0) {
$this->widths['max'] = $this->strlen($this->max);
$this->widths['current'] = $this->widths['max'];
} else {
$this->barCharOriginal = $this->barChar;
$this->barChar = $this->emptyBarChar;
}
}
private function generate($finish = false)
{
$vars = array();
$percent = 0;
if ($this->max > 0) {
$percent = (double) round($this->current / $this->max, 2);
}
if (isset($this->formatVars['bar'])) {
$completeBars = 0;
$emptyBars = 0;
if ($this->max > 0) {
$completeBars = floor($percent * $this->barWidth);
} else {
if (!$finish) {
$completeBars = floor($this->current % $this->barWidth);
} else {
$completeBars = $this->barWidth;
}
}
$emptyBars = $this->barWidth - $completeBars - $this->strlen($this->progressChar);
$bar = str_repeat($this->barChar, $completeBars);
if ($completeBars < $this->barWidth) {
$bar .= $this->progressChar;
$bar .= str_repeat($this->emptyBarChar, $emptyBars);
}
$vars['bar'] = $bar;
}
if (isset($this->formatVars['elapsed'])) {
$elapsed = time() - $this->startTime;
$vars['elapsed'] = str_pad($this->humaneTime($elapsed), $this->widths['elapsed'],' ', STR_PAD_LEFT);
}
if (isset($this->formatVars['current'])) {
$vars['current'] = str_pad($this->current, $this->widths['current'],' ', STR_PAD_LEFT);
}
if (isset($this->formatVars['max'])) {
$vars['max'] = $this->max;
}
if (isset($this->formatVars['percent'])) {
$vars['percent'] = str_pad($percent * 100, $this->widths['percent'],' ', STR_PAD_LEFT);
}
return $vars;
}
private function humaneTime($secs)
{
$text ='';
foreach ($this->timeFormats as $format) {
if ($secs < $format[0]) {
if (count($format) == 2) {
$text = $format[1];
break;
} else {
$text = ceil($secs / $format[2]).' '.$format[1];
break;
}
}
}
return $text;
}
private function overwrite(OutputInterface $output, $message)
{
$length = $this->strlen($message);
if (null !== $this->lastMessagesLength && $this->lastMessagesLength > $length) {
$message = str_pad($message, $this->lastMessagesLength,"\x20", STR_PAD_RIGHT);
}
$output->write("\x0D");
$output->write($message);
$this->lastMessagesLength = $this->strlen($message);
}
public function getName()
{
return'progress';
}
}
}
namespace Symfony\Component\Console\Helper
{
use Symfony\Component\Console\Output\OutputInterface;
use InvalidArgumentException;
class TableHelper extends Helper
{
const LAYOUT_DEFAULT = 0;
const LAYOUT_BORDERLESS = 1;
private $headers = array();
private $rows = array();
private $paddingChar;
private $horizontalBorderChar;
private $verticalBorderChar;
private $crossingChar;
private $cellHeaderFormat;
private $cellRowFormat;
private $borderFormat;
private $padType;
private $columnWidths = array();
private $numberOfColumns;
private $output;
public function __construct()
{
$this->setLayout(self::LAYOUT_DEFAULT);
}
public function setLayout($layout)
{
switch ($layout) {
case self::LAYOUT_BORDERLESS:
$this
->setPaddingChar(' ')
->setHorizontalBorderChar('=')
->setVerticalBorderChar(' ')
->setCrossingChar(' ')
->setCellHeaderFormat('<info>%s</info>')
->setCellRowFormat('<comment>%s</comment>')
->setBorderFormat('%s')
->setPadType(STR_PAD_RIGHT)
;
break;
case self::LAYOUT_DEFAULT:
$this
->setPaddingChar(' ')
->setHorizontalBorderChar('-')
->setVerticalBorderChar('|')
->setCrossingChar('+')
->setCellHeaderFormat('<info>%s</info>')
->setCellRowFormat('<comment>%s</comment>')
->setBorderFormat('%s')
->setPadType(STR_PAD_RIGHT)
;
break;
default:
throw new InvalidArgumentException(sprintf('Invalid table layout "%s".', $layout));
break;
};
return $this;
}
public function setHeaders(array $headers)
{
$this->headers = array_values($headers);
return $this;
}
public function setRows(array $rows)
{
$this->rows = array();
return $this->addRows($rows);
}
public function addRows(array $rows)
{
foreach ($rows as $row) {
$this->addRow($row);
}
return $this;
}
public function addRow(array $row)
{
$this->rows[] = array_values($row);
return $this;
}
public function setRow($column, array $row)
{
$this->rows[$column] = $row;
return $this;
}
public function setPaddingChar($paddingChar)
{
$this->paddingChar = $paddingChar;
return $this;
}
public function setHorizontalBorderChar($horizontalBorderChar)
{
$this->horizontalBorderChar = $horizontalBorderChar;
return $this;
}
public function setVerticalBorderChar($verticalBorderChar)
{
$this->verticalBorderChar = $verticalBorderChar;
return $this;
}
public function setCrossingChar($crossingChar)
{
$this->crossingChar = $crossingChar;
return $this;
}
public function setCellHeaderFormat($cellHeaderFormat)
{
$this->cellHeaderFormat = $cellHeaderFormat;
return $this;
}
public function setCellRowFormat($cellRowFormat)
{
$this->cellRowFormat = $cellRowFormat;
return $this;
}
public function setBorderFormat($borderFormat)
{
$this->borderFormat = $borderFormat;
return $this;
}
public function setPadType($padType)
{
$this->padType = $padType;
return $this;
}
public function render(OutputInterface $output)
{
$this->output = $output;
$this->renderRowSeparator();
$this->renderRow($this->headers, $this->cellHeaderFormat);
if (!empty($this->headers)) {
$this->renderRowSeparator();
}
foreach ($this->rows as $row) {
$this->renderRow($row, $this->cellRowFormat);
}
if (!empty($this->rows)) {
$this->renderRowSeparator();
}
$this->cleanup();
}
private function renderRowSeparator()
{
if (0 === $count = $this->getNumberOfColumns()) {
return;
}
$markup = $this->crossingChar;
for ($column = 0; $column < $count; $column++) {
$markup .= str_repeat($this->horizontalBorderChar, $this->getColumnWidth($column))
.$this->crossingChar
;
}
$this->output->writeln(sprintf($this->borderFormat, $markup));
}
private function renderColumnSeparator()
{
$this->output->write(sprintf($this->borderFormat, $this->verticalBorderChar));
}
private function renderRow(array $row, $cellFormat)
{
if (empty($row)) {
return;
}
$this->renderColumnSeparator();
for ($column = 0, $count = $this->getNumberOfColumns(); $column < $count; $column++) {
$this->renderCell($row, $column, $cellFormat);
$this->renderColumnSeparator();
}
$this->output->writeln('');
}
private function renderCell(array $row, $column, $cellFormat)
{
$cell = isset($row[$column]) ? $row[$column] :'';
$this->output->write(sprintf(
$cellFormat,
str_pad(
$this->paddingChar.$cell.$this->paddingChar,
$this->getColumnWidth($column),
$this->paddingChar,
$this->padType
)
));
}
private function getNumberOfColumns()
{
if (null !== $this->numberOfColumns) {
return $this->numberOfColumns;
}
$columns = array(0);
$columns[] = count($this->headers);
foreach ($this->rows as $row) {
$columns[] = count($row);
}
return $this->numberOfColumns = max($columns);
}
private function getColumnWidth($column)
{
if (isset($this->columnWidths[$column])) {
return $this->columnWidths[$column];
}
$lengths = array(0);
$lengths[] = $this->getCellWidth($this->headers, $column);
foreach ($this->rows as $row) {
$lengths[] = $this->getCellWidth($row, $column);
}
return $this->columnWidths[$column] = max($lengths) + 2;
}
private function getCellWidth(array $row, $column)
{
if ($column < 0) {
return 0;
}
if (isset($row[$column])) {
return $this->strlen($row[$column]);
}
return $this->getCellWidth($row, $column - 1);
}
private function cleanup()
{
$this->columnWidths = array();
$this->numberOfColumns = null;
}
public function getName()
{
return'table';
}
}
}
namespace Symfony\Component\Console\Input
{
interface InputInterface
{
public function getFirstArgument();
public function hasParameterOption($values);
public function getParameterOption($values, $default = false);
public function bind(InputDefinition $definition);
public function validate();
public function getArguments();
public function getArgument($name);
public function setArgument($name, $value);
public function hasArgument($name);
public function getOptions();
public function getOption($name);
public function setOption($name, $value);
public function hasOption($name);
public function isInteractive();
public function setInteractive($interactive);
}
}
namespace Symfony\Component\Console\Input
{
abstract class Input implements InputInterface
{
protected $definition;
protected $options;
protected $arguments;
protected $interactive = true;
public function __construct(InputDefinition $definition = null)
{
if (null === $definition) {
$this->arguments = array();
$this->options = array();
$this->definition = new InputDefinition();
} else {
$this->bind($definition);
$this->validate();
}
}
public function bind(InputDefinition $definition)
{
$this->arguments = array();
$this->options = array();
$this->definition = $definition;
$this->parse();
}
abstract protected function parse();
public function validate()
{
if (count($this->arguments) < $this->definition->getArgumentRequiredCount()) {
throw new \RuntimeException('Not enough arguments.');
}
}
public function isInteractive()
{
return $this->interactive;
}
public function setInteractive($interactive)
{
$this->interactive = (Boolean) $interactive;
}
public function getArguments()
{
return array_merge($this->definition->getArgumentDefaults(), $this->arguments);
}
public function getArgument($name)
{
if (!$this->definition->hasArgument($name)) {
throw new \InvalidArgumentException(sprintf('The "%s" argument does not exist.', $name));
}
return isset($this->arguments[$name]) ? $this->arguments[$name] : $this->definition->getArgument($name)->getDefault();
}
public function setArgument($name, $value)
{
if (!$this->definition->hasArgument($name)) {
throw new \InvalidArgumentException(sprintf('The "%s" argument does not exist.', $name));
}
$this->arguments[$name] = $value;
}
public function hasArgument($name)
{
return $this->definition->hasArgument($name);
}
public function getOptions()
{
return array_merge($this->definition->getOptionDefaults(), $this->options);
}
public function getOption($name)
{
if (!$this->definition->hasOption($name)) {
throw new \InvalidArgumentException(sprintf('The "%s" option does not exist.', $name));
}
return isset($this->options[$name]) ? $this->options[$name] : $this->definition->getOption($name)->getDefault();
}
public function setOption($name, $value)
{
if (!$this->definition->hasOption($name)) {
throw new \InvalidArgumentException(sprintf('The "%s" option does not exist.', $name));
}
$this->options[$name] = $value;
}
public function hasOption($name)
{
return $this->definition->hasOption($name);
}
public function escapeToken($token)
{
return preg_match('{^[\w-]+$}', $token) ? $token : escapeshellarg($token);
}
}
}
namespace Symfony\Component\Console\Input
{
class ArgvInput extends Input
{
private $tokens;
private $parsed;
public function __construct(array $argv = null, InputDefinition $definition = null)
{
if (null === $argv) {
$argv = $_SERVER['argv'];
}
array_shift($argv);
$this->tokens = $argv;
parent::__construct($definition);
}
protected function setTokens(array $tokens)
{
$this->tokens = $tokens;
}
protected function parse()
{
$parseOptions = true;
$this->parsed = $this->tokens;
while (null !== $token = array_shift($this->parsed)) {
if ($parseOptions &&''== $token) {
$this->parseArgument($token);
} elseif ($parseOptions &&'--'== $token) {
$parseOptions = false;
} elseif ($parseOptions && 0 === strpos($token,'--')) {
$this->parseLongOption($token);
} elseif ($parseOptions &&'-'=== $token[0]) {
$this->parseShortOption($token);
} else {
$this->parseArgument($token);
}
}
}
private function parseShortOption($token)
{
$name = substr($token, 1);
if (strlen($name) > 1) {
if ($this->definition->hasShortcut($name[0]) && $this->definition->getOptionForShortcut($name[0])->acceptValue()) {
$this->addShortOption($name[0], substr($name, 1));
} else {
$this->parseShortOptionSet($name);
}
} else {
$this->addShortOption($name, null);
}
}
private function parseShortOptionSet($name)
{
$len = strlen($name);
for ($i = 0; $i < $len; $i++) {
if (!$this->definition->hasShortcut($name[$i])) {
throw new \RuntimeException(sprintf('The "-%s" option does not exist.', $name[$i]));
}
$option = $this->definition->getOptionForShortcut($name[$i]);
if ($option->acceptValue()) {
$this->addLongOption($option->getName(), $i === $len - 1 ? null : substr($name, $i + 1));
break;
} else {
$this->addLongOption($option->getName(), true);
}
}
}
private function parseLongOption($token)
{
$name = substr($token, 2);
if (false !== $pos = strpos($name,'=')) {
$this->addLongOption(substr($name, 0, $pos), substr($name, $pos + 1));
} else {
$this->addLongOption($name, null);
}
}
private function parseArgument($token)
{
$c = count($this->arguments);
if ($this->definition->hasArgument($c)) {
$arg = $this->definition->getArgument($c);
$this->arguments[$arg->getName()] = $arg->isArray()? array($token) : $token;
} elseif ($this->definition->hasArgument($c - 1) && $this->definition->getArgument($c - 1)->isArray()) {
$arg = $this->definition->getArgument($c - 1);
$this->arguments[$arg->getName()][] = $token;
} else {
throw new \RuntimeException('Too many arguments.');
}
}
private function addShortOption($shortcut, $value)
{
if (!$this->definition->hasShortcut($shortcut)) {
throw new \RuntimeException(sprintf('The "-%s" option does not exist.', $shortcut));
}
$this->addLongOption($this->definition->getOptionForShortcut($shortcut)->getName(), $value);
}
private function addLongOption($name, $value)
{
if (!$this->definition->hasOption($name)) {
throw new \RuntimeException(sprintf('The "--%s" option does not exist.', $name));
}
$option = $this->definition->getOption($name);
if (false === $value) {
$value = null;
}
if (null === $value && $option->acceptValue() && count($this->parsed)) {
$next = array_shift($this->parsed);
if (isset($next[0]) &&'-'!== $next[0]) {
$value = $next;
} elseif (empty($next)) {
$value ='';
} else {
array_unshift($this->parsed, $next);
}
}
if (null === $value) {
if ($option->isValueRequired()) {
throw new \RuntimeException(sprintf('The "--%s" option requires a value.', $name));
}
if (!$option->isArray()) {
$value = $option->isValueOptional() ? $option->getDefault() : true;
}
}
if ($option->isArray()) {
$this->options[$name][] = $value;
} else {
$this->options[$name] = $value;
}
}
public function getFirstArgument()
{
foreach ($this->tokens as $token) {
if ($token &&'-'=== $token[0]) {
continue;
}
return $token;
}
}
public function hasParameterOption($values)
{
$values = (array) $values;
foreach ($this->tokens as $v) {
if (in_array($v, $values)) {
return true;
}
}
return false;
}
public function getParameterOption($values, $default = false)
{
$values = (array) $values;
$tokens = $this->tokens;
while ($token = array_shift($tokens)) {
foreach ($values as $value) {
if (0 === strpos($token, $value)) {
if (false !== $pos = strpos($token,'=')) {
return substr($token, $pos + 1);
}
return array_shift($tokens);
}
}
}
return $default;
}
public function __toString()
{
$self = $this;
$tokens = array_map(function ($token) use ($self) {
if (preg_match('{^(-[^=]+=)(.+)}', $token, $match)) {
return $match[1] . $self->escapeToken($match[2]);
}
if ($token && $token[0] !=='-') {
return $self->escapeToken($token);
}
return $token;
}, $this->tokens);
return implode(' ', $tokens);
}
}
}
namespace Symfony\Component\Console\Input
{
class ArrayInput extends Input
{
private $parameters;
public function __construct(array $parameters, InputDefinition $definition = null)
{
$this->parameters = $parameters;
parent::__construct($definition);
}
public function getFirstArgument()
{
foreach ($this->parameters as $key => $value) {
if ($key &&'-'=== $key[0]) {
continue;
}
return $value;
}
}
public function hasParameterOption($values)
{
$values = (array) $values;
foreach ($this->parameters as $k => $v) {
if (!is_int($k)) {
$v = $k;
}
if (in_array($v, $values)) {
return true;
}
}
return false;
}
public function getParameterOption($values, $default = false)
{
$values = (array) $values;
foreach ($this->parameters as $k => $v) {
if (is_int($k) && in_array($v, $values)) {
return true;
} elseif (in_array($k, $values)) {
return $v;
}
}
return $default;
}
public function __toString()
{
$params = array();
foreach ($this->parameters as $param => $val) {
if ($param &&'-'=== $param[0]) {
$params[] = $param . (''!= $val ?'='.$this->escapeToken($val) :'');
} else {
$params[] = $this->escapeToken($val);
}
}
return implode(' ', $params);
}
protected function parse()
{
foreach ($this->parameters as $key => $value) {
if (0 === strpos($key,'--')) {
$this->addLongOption(substr($key, 2), $value);
} elseif ('-'=== $key[0]) {
$this->addShortOption(substr($key, 1), $value);
} else {
$this->addArgument($key, $value);
}
}
}
private function addShortOption($shortcut, $value)
{
if (!$this->definition->hasShortcut($shortcut)) {
throw new \InvalidArgumentException(sprintf('The "-%s" option does not exist.', $shortcut));
}
$this->addLongOption($this->definition->getOptionForShortcut($shortcut)->getName(), $value);
}
private function addLongOption($name, $value)
{
if (!$this->definition->hasOption($name)) {
throw new \InvalidArgumentException(sprintf('The "--%s" option does not exist.', $name));
}
$option = $this->definition->getOption($name);
if (null === $value) {
if ($option->isValueRequired()) {
throw new \InvalidArgumentException(sprintf('The "--%s" option requires a value.', $name));
}
$value = $option->isValueOptional() ? $option->getDefault() : true;
}
$this->options[$name] = $value;
}
private function addArgument($name, $value)
{
if (!$this->definition->hasArgument($name)) {
throw new \InvalidArgumentException(sprintf('The "%s" argument does not exist.', $name));
}
$this->arguments[$name] = $value;
}
}
}
namespace Symfony\Component\Console\Input
{
class InputArgument
{
const REQUIRED = 1;
const OPTIONAL = 2;
const IS_ARRAY = 4;
private $name;
private $mode;
private $default;
private $description;
public function __construct($name, $mode = null, $description ='', $default = null)
{
if (null === $mode) {
$mode = self::OPTIONAL;
} elseif (!is_int($mode) || $mode > 7 || $mode < 1) {
throw new \InvalidArgumentException(sprintf('Argument mode "%s" is not valid.', $mode));
}
$this->name = $name;
$this->mode = $mode;
$this->description = $description;
$this->setDefault($default);
}
public function getName()
{
return $this->name;
}
public function isRequired()
{
return self::REQUIRED === (self::REQUIRED & $this->mode);
}
public function isArray()
{
return self::IS_ARRAY === (self::IS_ARRAY & $this->mode);
}
public function setDefault($default = null)
{
if (self::REQUIRED === $this->mode && null !== $default) {
throw new \LogicException('Cannot set a default value except for InputArgument::OPTIONAL mode.');
}
if ($this->isArray()) {
if (null === $default) {
$default = array();
} elseif (!is_array($default)) {
throw new \LogicException('A default value for an array argument must be an array.');
}
}
$this->default = $default;
}
public function getDefault()
{
return $this->default;
}
public function getDescription()
{
return $this->description;
}
}
}
namespace Symfony\Component\Console\Input
{
use Symfony\Component\Console\Descriptor\TextDescriptor;
use Symfony\Component\Console\Descriptor\XmlDescriptor;
class InputDefinition
{
private $arguments;
private $requiredCount;
private $hasAnArrayArgument = false;
private $hasOptional;
private $options;
private $shortcuts;
public function __construct(array $definition = array())
{
$this->setDefinition($definition);
}
public function setDefinition(array $definition)
{
$arguments = array();
$options = array();
foreach ($definition as $item) {
if ($item instanceof InputOption) {
$options[] = $item;
} else {
$arguments[] = $item;
}
}
$this->setArguments($arguments);
$this->setOptions($options);
}
public function setArguments($arguments = array())
{
$this->arguments = array();
$this->requiredCount = 0;
$this->hasOptional = false;
$this->hasAnArrayArgument = false;
$this->addArguments($arguments);
}
public function addArguments($arguments = array())
{
if (null !== $arguments) {
foreach ($arguments as $argument) {
$this->addArgument($argument);
}
}
}
public function addArgument(InputArgument $argument)
{
if (isset($this->arguments[$argument->getName()])) {
throw new \LogicException(sprintf('An argument with name "%s" already exists.', $argument->getName()));
}
if ($this->hasAnArrayArgument) {
throw new \LogicException('Cannot add an argument after an array argument.');
}
if ($argument->isRequired() && $this->hasOptional) {
throw new \LogicException('Cannot add a required argument after an optional one.');
}
if ($argument->isArray()) {
$this->hasAnArrayArgument = true;
}
if ($argument->isRequired()) {
++$this->requiredCount;
} else {
$this->hasOptional = true;
}
$this->arguments[$argument->getName()] = $argument;
}
public function getArgument($name)
{
if (!$this->hasArgument($name)) {
throw new \InvalidArgumentException(sprintf('The "%s" argument does not exist.', $name));
}
$arguments = is_int($name) ? array_values($this->arguments) : $this->arguments;
return $arguments[$name];
}
public function hasArgument($name)
{
$arguments = is_int($name) ? array_values($this->arguments) : $this->arguments;
return isset($arguments[$name]);
}
public function getArguments()
{
return $this->arguments;
}
public function getArgumentCount()
{
return $this->hasAnArrayArgument ? PHP_INT_MAX : count($this->arguments);
}
public function getArgumentRequiredCount()
{
return $this->requiredCount;
}
public function getArgumentDefaults()
{
$values = array();
foreach ($this->arguments as $argument) {
$values[$argument->getName()] = $argument->getDefault();
}
return $values;
}
public function setOptions($options = array())
{
$this->options = array();
$this->shortcuts = array();
$this->addOptions($options);
}
public function addOptions($options = array())
{
foreach ($options as $option) {
$this->addOption($option);
}
}
public function addOption(InputOption $option)
{
if (isset($this->options[$option->getName()]) && !$option->equals($this->options[$option->getName()])) {
throw new \LogicException(sprintf('An option named "%s" already exists.', $option->getName()));
}
if ($option->getShortcut()) {
foreach (explode('|', $option->getShortcut()) as $shortcut) {
if (isset($this->shortcuts[$shortcut]) && !$option->equals($this->options[$this->shortcuts[$shortcut]])) {
throw new \LogicException(sprintf('An option with shortcut "%s" already exists.', $shortcut));
}
}
}
$this->options[$option->getName()] = $option;
if ($option->getShortcut()) {
foreach (explode('|', $option->getShortcut()) as $shortcut) {
$this->shortcuts[$shortcut] = $option->getName();
}
}
}
public function getOption($name)
{
if (!$this->hasOption($name)) {
throw new \InvalidArgumentException(sprintf('The "--%s" option does not exist.', $name));
}
return $this->options[$name];
}
public function hasOption($name)
{
return isset($this->options[$name]);
}
public function getOptions()
{
return $this->options;
}
public function hasShortcut($name)
{
return isset($this->shortcuts[$name]);
}
public function getOptionForShortcut($shortcut)
{
return $this->getOption($this->shortcutToName($shortcut));
}
public function getOptionDefaults()
{
$values = array();
foreach ($this->options as $option) {
$values[$option->getName()] = $option->getDefault();
}
return $values;
}
private function shortcutToName($shortcut)
{
if (!isset($this->shortcuts[$shortcut])) {
throw new \InvalidArgumentException(sprintf('The "-%s" option does not exist.', $shortcut));
}
return $this->shortcuts[$shortcut];
}
public function getSynopsis()
{
$elements = array();
foreach ($this->getOptions() as $option) {
$shortcut = $option->getShortcut() ? sprintf('-%s|', $option->getShortcut()) :'';
$elements[] = sprintf('['.($option->isValueRequired() ?'%s--%s="..."': ($option->isValueOptional() ?'%s--%s[="..."]':'%s--%s')).']', $shortcut, $option->getName());
}
foreach ($this->getArguments() as $argument) {
$elements[] = sprintf($argument->isRequired() ?'%s':'[%s]', $argument->getName().($argument->isArray() ?'1':''));
if ($argument->isArray()) {
$elements[] = sprintf('... [%sN]', $argument->getName());
}
}
return implode(' ', $elements);
}
public function asText()
{
$descriptor = new TextDescriptor();
return $descriptor->describe($this);
}
public function asXml($asDom = false)
{
$descriptor = new XmlDescriptor();
return $descriptor->describe($this, array('as_dom'=> $asDom));
}
}
}
namespace Symfony\Component\Console\Input
{
class InputOption
{
const VALUE_NONE = 1;
const VALUE_REQUIRED = 2;
const VALUE_OPTIONAL = 4;
const VALUE_IS_ARRAY = 8;
private $name;
private $shortcut;
private $mode;
private $default;
private $description;
public function __construct($name, $shortcut = null, $mode = null, $description ='', $default = null)
{
if (0 === strpos($name,'--')) {
$name = substr($name, 2);
}
if (empty($name)) {
throw new \InvalidArgumentException('An option name cannot be empty.');
}
if (empty($shortcut)) {
$shortcut = null;
}
if (null !== $shortcut) {
if (is_array($shortcut)) {
$shortcut = implode('|', $shortcut);
}
$shortcuts = preg_split('{(\|)-?}', ltrim($shortcut,'-'));
$shortcuts = array_filter($shortcuts);
$shortcut = implode('|', $shortcuts);
if (empty($shortcut)) {
throw new \InvalidArgumentException('An option shortcut cannot be empty.');
}
}
if (null === $mode) {
$mode = self::VALUE_NONE;
} elseif (!is_int($mode) || $mode > 15 || $mode < 1) {
throw new \InvalidArgumentException(sprintf('Option mode "%s" is not valid.', $mode));
}
$this->name = $name;
$this->shortcut = $shortcut;
$this->mode = $mode;
$this->description = $description;
if ($this->isArray() && !$this->acceptValue()) {
throw new \InvalidArgumentException('Impossible to have an option mode VALUE_IS_ARRAY if the option does not accept a value.');
}
$this->setDefault($default);
}
public function getShortcut()
{
return $this->shortcut;
}
public function getName()
{
return $this->name;
}
public function acceptValue()
{
return $this->isValueRequired() || $this->isValueOptional();
}
public function isValueRequired()
{
return self::VALUE_REQUIRED === (self::VALUE_REQUIRED & $this->mode);
}
public function isValueOptional()
{
return self::VALUE_OPTIONAL === (self::VALUE_OPTIONAL & $this->mode);
}
public function isArray()
{
return self::VALUE_IS_ARRAY === (self::VALUE_IS_ARRAY & $this->mode);
}
public function setDefault($default = null)
{
if (self::VALUE_NONE === (self::VALUE_NONE & $this->mode) && null !== $default) {
throw new \LogicException('Cannot set a default value when using InputOption::VALUE_NONE mode.');
}
if ($this->isArray()) {
if (null === $default) {
$default = array();
} elseif (!is_array($default)) {
throw new \LogicException('A default value for an array option must be an array.');
}
}
$this->default = $this->acceptValue() ? $default : false;
}
public function getDefault()
{
return $this->default;
}
public function getDescription()
{
return $this->description;
}
public function equals(InputOption $option)
{
return $option->getName() === $this->getName()
&& $option->getShortcut() === $this->getShortcut()
&& $option->getDefault() === $this->getDefault()
&& $option->isArray() === $this->isArray()
&& $option->isValueRequired() === $this->isValueRequired()
&& $option->isValueOptional() === $this->isValueOptional()
;
}
}
}
namespace Symfony\Component\Console\Input
{
class StringInput extends ArgvInput
{
const REGEX_STRING ='([^\s]+?)(?:\s|(?<!\\\\)"|(?<!\\\\)\'|$)';
const REGEX_QUOTED_STRING ='(?:"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\')';
public function __construct($input, InputDefinition $definition = null)
{
parent::__construct(array(), null);
$this->setTokens($this->tokenize($input));
if (null !== $definition) {
$this->bind($definition);
}
}
private function tokenize($input)
{
$tokens = array();
$length = strlen($input);
$cursor = 0;
while ($cursor < $length) {
if (preg_match('/\s+/A', $input, $match, null, $cursor)) {
} elseif (preg_match('/([^="\'\s]+?)(=?)('.self::REGEX_QUOTED_STRING.'+)/A', $input, $match, null, $cursor)) {
$tokens[] = $match[1].$match[2].stripcslashes(str_replace(array('"\'','\'"','\'\'','""'),'', substr($match[3], 1, strlen($match[3]) - 2)));
} elseif (preg_match('/'.self::REGEX_QUOTED_STRING.'/A', $input, $match, null, $cursor)) {
$tokens[] = stripcslashes(substr($match[0], 1, strlen($match[0]) - 2));
} elseif (preg_match('/'.self::REGEX_STRING.'/A', $input, $match, null, $cursor)) {
$tokens[] = stripcslashes($match[1]);
} else {
throw new \InvalidArgumentException(sprintf('Unable to parse input near "... %s ..."', substr($input, $cursor, 10)));
}
$cursor += strlen($match[0]);
}
return $tokens;
}
}
}
namespace Symfony\Component\Console\Output
{
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
interface OutputInterface
{
const VERBOSITY_QUIET = 0;
const VERBOSITY_NORMAL = 1;
const VERBOSITY_VERBOSE = 2;
const VERBOSITY_VERY_VERBOSE = 3;
const VERBOSITY_DEBUG = 4;
const OUTPUT_NORMAL = 0;
const OUTPUT_RAW = 1;
const OUTPUT_PLAIN = 2;
public function write($messages, $newline = false, $type = self::OUTPUT_NORMAL);
public function writeln($messages, $type = self::OUTPUT_NORMAL);
public function setVerbosity($level);
public function getVerbosity();
public function setDecorated($decorated);
public function isDecorated();
public function setFormatter(OutputFormatterInterface $formatter);
public function getFormatter();
}
}
namespace Symfony\Component\Console\Output
{
use Symfony\Component\Console\Output\OutputInterface;
interface ConsoleOutputInterface extends OutputInterface
{
public function getErrorOutput();
public function setErrorOutput(OutputInterface $error);
}
}
namespace Symfony\Component\Console\Output
{
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatter;
abstract class Output implements OutputInterface
{
private $verbosity;
private $formatter;
public function __construct($verbosity = self::VERBOSITY_NORMAL, $decorated = false, OutputFormatterInterface $formatter = null)
{
$this->verbosity = null === $verbosity ? self::VERBOSITY_NORMAL : $verbosity;
$this->formatter = null === $formatter ? new OutputFormatter() : $formatter;
$this->formatter->setDecorated($decorated);
}
public function setFormatter(OutputFormatterInterface $formatter)
{
$this->formatter = $formatter;
}
public function getFormatter()
{
return $this->formatter;
}
public function setDecorated($decorated)
{
$this->formatter->setDecorated($decorated);
}
public function isDecorated()
{
return $this->formatter->isDecorated();
}
public function setVerbosity($level)
{
$this->verbosity = (int) $level;
}
public function getVerbosity()
{
return $this->verbosity;
}
public function writeln($messages, $type = self::OUTPUT_NORMAL)
{
$this->write($messages, true, $type);
}
public function write($messages, $newline = false, $type = self::OUTPUT_NORMAL)
{
if (self::VERBOSITY_QUIET === $this->verbosity) {
return;
}
$messages = (array) $messages;
foreach ($messages as $message) {
switch ($type) {
case OutputInterface::OUTPUT_NORMAL:
$message = $this->formatter->format($message);
break;
case OutputInterface::OUTPUT_RAW:
break;
case OutputInterface::OUTPUT_PLAIN:
$message = strip_tags($this->formatter->format($message));
break;
default:
throw new \InvalidArgumentException(sprintf('Unknown output type given (%s)', $type));
}
$this->doWrite($message, $newline);
}
}
abstract protected function doWrite($message, $newline);
}
}
namespace Symfony\Component\Console\Output
{
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
class StreamOutput extends Output
{
private $stream;
public function __construct($stream, $verbosity = self::VERBOSITY_NORMAL, $decorated = null, OutputFormatterInterface $formatter = null)
{
if (!is_resource($stream) ||'stream'!== get_resource_type($stream)) {
throw new \InvalidArgumentException('The StreamOutput class needs a stream as its first argument.');
}
$this->stream = $stream;
if (null === $decorated) {
$decorated = $this->hasColorSupport();
}
parent::__construct($verbosity, $decorated, $formatter);
}
public function getStream()
{
return $this->stream;
}
protected function doWrite($message, $newline)
{
if (false === @fwrite($this->stream, $message.($newline ? PHP_EOL :''))) {
throw new \RuntimeException('Unable to write output.');
}
fflush($this->stream);
}
protected function hasColorSupport()
{
if (DIRECTORY_SEPARATOR =='\\') {
return false !== getenv('ANSICON') ||'ON'=== getenv('ConEmuANSI');
}
return function_exists('posix_isatty') && @posix_isatty($this->stream);
}
}
}
namespace Symfony\Component\Console\Output
{
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
class ConsoleOutput extends StreamOutput implements ConsoleOutputInterface
{
private $stderr;
public function __construct($verbosity = self::VERBOSITY_NORMAL, $decorated = null, OutputFormatterInterface $formatter = null)
{
$outputStream ='php://stdout';
if (!$this->hasStdoutSupport()) {
$outputStream ='php://output';
}
parent::__construct(fopen($outputStream,'w'), $verbosity, $decorated, $formatter);
$this->stderr = new StreamOutput(fopen('php://stderr','w'), $verbosity, $decorated, $formatter);
}
public function setDecorated($decorated)
{
parent::setDecorated($decorated);
$this->stderr->setDecorated($decorated);
}
public function setFormatter(OutputFormatterInterface $formatter)
{
parent::setFormatter($formatter);
$this->stderr->setFormatter($formatter);
}
public function setVerbosity($level)
{
parent::setVerbosity($level);
$this->stderr->setVerbosity($level);
}
public function getErrorOutput()
{
return $this->stderr;
}
public function setErrorOutput(OutputInterface $error)
{
$this->stderr = $error;
}
protected function hasStdoutSupport()
{
return ('OS400'!= php_uname('s'));
}
}
}
namespace Symfony\Component\Console\Output
{
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
class NullOutput implements OutputInterface
{
public function setFormatter(OutputFormatterInterface $formatter)
{
}
public function getFormatter()
{
return new OutputFormatter();
}
public function setDecorated($decorated)
{
}
public function isDecorated()
{
return false;
}
public function setVerbosity($level)
{
}
public function getVerbosity()
{
return self::VERBOSITY_QUIET;
}
public function writeln($messages, $type = self::OUTPUT_NORMAL)
{
}
public function write($messages, $newline = false, $type = self::OUTPUT_NORMAL)
{
}
}
}
namespace Symfony\Component\Console
{
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Process\PhpExecutableFinder;
class Shell
{
private $application;
private $history;
private $output;
private $hasReadline;
private $processIsolation;
public function __construct(Application $application)
{
$this->hasReadline = function_exists('readline');
$this->application = $application;
$this->history = getenv('HOME').'/.history_'.$application->getName();
$this->output = new ConsoleOutput();
$this->processIsolation = false;
}
public function run()
{
$this->application->setAutoExit(false);
$this->application->setCatchExceptions(true);
if ($this->hasReadline) {
readline_read_history($this->history);
readline_completion_function(array($this,'autocompleter'));
}
$this->output->writeln($this->getHeader());
$php = null;
if ($this->processIsolation) {
$finder = new PhpExecutableFinder();
$php = $finder->find();
$this->output->writeln(<<<EOF
<info>Running with process isolation, you should consider this:</info>
  * each command is executed as separate process,
  * commands don't support interactivity, all params must be passed explicitly,
  * commands output is not colorized.

EOF
);
}
while (true) {
$command = $this->readline();
if (false === $command) {
$this->output->writeln("\n");
break;
}
if ($this->hasReadline) {
readline_add_history($command);
readline_write_history($this->history);
}
if ($this->processIsolation) {
$pb = new ProcessBuilder();
$process = $pb
->add($php)
->add($_SERVER['argv'][0])
->add($command)
->inheritEnvironmentVariables(true)
->getProcess()
;
$output = $this->output;
$process->run(function($type, $data) use ($output) {
$output->writeln($data);
});
$ret = $process->getExitCode();
} else {
$ret = $this->application->run(new StringInput($command), $this->output);
}
if (0 !== $ret) {
$this->output->writeln(sprintf('<error>The command terminated with an error status (%s)</error>', $ret));
}
}
}
protected function getHeader()
{
return<<<EOF

Welcome to the <info>{$this->application->getName()}</info> shell (<comment>{$this->application->getVersion()}</comment>).

At the prompt, type <comment>help</comment> for some help,
or <comment>list</comment> to get a list of available commands.

To exit the shell, type <comment>^D</comment>.

EOF
;
}
protected function getPrompt()
{
return $this->output->getFormatter()->format($this->application->getName().' > ');
}
protected function getOutput()
{
return $this->output;
}
protected function getApplication()
{
return $this->application;
}
private function autocompleter($text)
{
$info = readline_info();
$text = substr($info['line_buffer'], 0, $info['end']);
if ($info['point'] !== $info['end']) {
return true;
}
if (false === strpos($text,' ') || !$text) {
return array_keys($this->application->all());
}
try {
$command = $this->application->find(substr($text, 0, strpos($text,' ')));
} catch (\Exception $e) {
return true;
}
$list = array('--help');
foreach ($command->getDefinition()->getOptions() as $option) {
$list[] ='--'.$option->getName();
}
return $list;
}
private function readline()
{
if ($this->hasReadline) {
$line = readline($this->getPrompt());
} else {
$this->output->write($this->getPrompt());
$line = fgets(STDIN, 1024);
$line = (!$line && strlen($line) == 0) ? false : rtrim($line);
}
return $line;
}
public function getProcessIsolation()
{
return $this->processIsolation;
}
public function setProcessIsolation($processIsolation)
{
$this->processIsolation = (Boolean) $processIsolation;
if ($this->processIsolation && !class_exists('Symfony\\Component\\Process\\Process')) {
throw new \RuntimeException('Unable to isolate processes as the Symfony Process Component is not installed.');
}
}
}
}
namespace
{
class Twig_Autoloader
{
public static function register($prepend = false)
{
if (version_compare(phpversion(),'5.3.0','>=')) {
spl_autoload_register(array(new self,'autoload'), true, $prepend);
} else {
spl_autoload_register(array(new self,'autoload'));
}
}
public static function autoload($class)
{
if (0 !== strpos($class,'Twig')) {
return;
}
if (is_file($file = dirname(__FILE__).'/../'.str_replace(array('_',"\0"), array('/',''), $class).'.php')) {
require $file;
}
}
}
}
namespace
{
interface Twig_CompilerInterface
{
public function compile(Twig_NodeInterface $node);
public function getSource();
}
}
namespace
{
class Twig_Compiler implements Twig_CompilerInterface
{
protected $lastLine;
protected $source;
protected $indentation;
protected $env;
protected $debugInfo;
protected $sourceOffset;
protected $sourceLine;
protected $filename;
public function __construct(Twig_Environment $env)
{
$this->env = $env;
$this->debugInfo = array();
}
public function getFilename()
{
return $this->filename;
}
public function getEnvironment()
{
return $this->env;
}
public function getSource()
{
return $this->source;
}
public function compile(Twig_NodeInterface $node, $indentation = 0)
{
$this->lastLine = null;
$this->source ='';
$this->sourceOffset = 0;
$this->sourceLine = 1;
$this->indentation = $indentation;
if ($node instanceof Twig_Node_Module) {
$this->filename = $node->getAttribute('filename');
}
$node->compile($this);
return $this;
}
public function subcompile(Twig_NodeInterface $node, $raw = true)
{
if (false === $raw) {
$this->addIndentation();
}
$node->compile($this);
return $this;
}
public function raw($string)
{
$this->source .= $string;
return $this;
}
public function write()
{
$strings = func_get_args();
foreach ($strings as $string) {
$this->addIndentation();
$this->source .= $string;
}
return $this;
}
public function addIndentation()
{
$this->source .= str_repeat(' ', $this->indentation * 4);
return $this;
}
public function string($value)
{
$this->source .= sprintf('"%s"', addcslashes($value,"\0\t\"\$\\"));
return $this;
}
public function repr($value)
{
if (is_int($value) || is_float($value)) {
if (false !== $locale = setlocale(LC_NUMERIC, 0)) {
setlocale(LC_NUMERIC,'C');
}
$this->raw($value);
if (false !== $locale) {
setlocale(LC_NUMERIC, $locale);
}
} elseif (null === $value) {
$this->raw('null');
} elseif (is_bool($value)) {
$this->raw($value ?'true':'false');
} elseif (is_array($value)) {
$this->raw('array(');
$i = 0;
foreach ($value as $key => $value) {
if ($i++) {
$this->raw(', ');
}
$this->repr($key);
$this->raw(' => ');
$this->repr($value);
}
$this->raw(')');
} else {
$this->string($value);
}
return $this;
}
public function addDebugInfo(Twig_NodeInterface $node)
{
if ($node->getLine() != $this->lastLine) {
$this->write("// line {$node->getLine()}\n");
if (((int) ini_get('mbstring.func_overload')) & 2) {
$this->sourceLine += mb_substr_count(mb_substr($this->source, $this->sourceOffset),"\n");
} else {
$this->sourceLine += substr_count($this->source,"\n", $this->sourceOffset);
}
$this->sourceOffset = strlen($this->source);
$this->debugInfo[$this->sourceLine] = $node->getLine();
$this->lastLine = $node->getLine();
}
return $this;
}
public function getDebugInfo()
{
return $this->debugInfo;
}
public function indent($step = 1)
{
$this->indentation += $step;
return $this;
}
public function outdent($step = 1)
{
if ($this->indentation < $step) {
throw new LogicException('Unable to call outdent() as the indentation would become negative');
}
$this->indentation -= $step;
return $this;
}
}
}
namespace
{
class Twig_Environment
{
const VERSION ='1.13.1';
protected $charset;
protected $loader;
protected $debug;
protected $autoReload;
protected $cache;
protected $lexer;
protected $parser;
protected $compiler;
protected $baseTemplateClass;
protected $extensions;
protected $parsers;
protected $visitors;
protected $filters;
protected $tests;
protected $functions;
protected $globals;
protected $runtimeInitialized;
protected $extensionInitialized;
protected $loadedTemplates;
protected $strictVariables;
protected $unaryOperators;
protected $binaryOperators;
protected $templateClassPrefix ='__TwigTemplate_';
protected $functionCallbacks;
protected $filterCallbacks;
protected $staging;
public function __construct(Twig_LoaderInterface $loader = null, $options = array())
{
if (null !== $loader) {
$this->setLoader($loader);
}
$options = array_merge(array('debug'=> false,'charset'=>'UTF-8','base_template_class'=>'Twig_Template','strict_variables'=> false,'autoescape'=>'html','cache'=> false,'auto_reload'=> null,'optimizations'=> -1,
), $options);
$this->debug = (bool) $options['debug'];
$this->charset = strtoupper($options['charset']);
$this->baseTemplateClass = $options['base_template_class'];
$this->autoReload = null === $options['auto_reload'] ? $this->debug : (bool) $options['auto_reload'];
$this->strictVariables = (bool) $options['strict_variables'];
$this->runtimeInitialized = false;
$this->setCache($options['cache']);
$this->functionCallbacks = array();
$this->filterCallbacks = array();
$this->addExtension(new Twig_Extension_Core());
$this->addExtension(new Twig_Extension_Escaper($options['autoescape']));
$this->addExtension(new Twig_Extension_Optimizer($options['optimizations']));
$this->extensionInitialized = false;
$this->staging = new Twig_Extension_Staging();
}
public function getBaseTemplateClass()
{
return $this->baseTemplateClass;
}
public function setBaseTemplateClass($class)
{
$this->baseTemplateClass = $class;
}
public function enableDebug()
{
$this->debug = true;
}
public function disableDebug()
{
$this->debug = false;
}
public function isDebug()
{
return $this->debug;
}
public function enableAutoReload()
{
$this->autoReload = true;
}
public function disableAutoReload()
{
$this->autoReload = false;
}
public function isAutoReload()
{
return $this->autoReload;
}
public function enableStrictVariables()
{
$this->strictVariables = true;
}
public function disableStrictVariables()
{
$this->strictVariables = false;
}
public function isStrictVariables()
{
return $this->strictVariables;
}
public function getCache()
{
return $this->cache;
}
public function setCache($cache)
{
$this->cache = $cache ? $cache : false;
}
public function getCacheFilename($name)
{
if (false === $this->cache) {
return false;
}
$class = substr($this->getTemplateClass($name), strlen($this->templateClassPrefix));
return $this->getCache().'/'.substr($class, 0, 2).'/'.substr($class, 2, 2).'/'.substr($class, 4).'.php';
}
public function getTemplateClass($name, $index = null)
{
return $this->templateClassPrefix.md5($this->getLoader()->getCacheKey($name)).(null === $index ?'':'_'.$index);
}
public function getTemplateClassPrefix()
{
return $this->templateClassPrefix;
}
public function render($name, array $context = array())
{
return $this->loadTemplate($name)->render($context);
}
public function display($name, array $context = array())
{
$this->loadTemplate($name)->display($context);
}
public function loadTemplate($name, $index = null)
{
$cls = $this->getTemplateClass($name, $index);
if (isset($this->loadedTemplates[$cls])) {
return $this->loadedTemplates[$cls];
}
if (!class_exists($cls, false)) {
if (false === $cache = $this->getCacheFilename($name)) {
eval($this->compileSource($this->getLoader()->getSource($name), $name));
} else {
if (!is_file($cache) || ($this->isAutoReload() && !$this->isTemplateFresh($name, filemtime($cache)))) {
$this->writeCacheFile($cache, $this->compileSource($this->getLoader()->getSource($name), $name));
}

}
}
if (!$this->runtimeInitialized) {
$this->initRuntime();
}
return $this->loadedTemplates[$cls] = new $cls($this);
}
public function isTemplateFresh($name, $time)
{
foreach ($this->extensions as $extension) {
$r = new ReflectionObject($extension);
if (filemtime($r->getFileName()) > $time) {
return false;
}
}
return $this->getLoader()->isFresh($name, $time);
}
public function resolveTemplate($names)
{
if (!is_array($names)) {
$names = array($names);
}
foreach ($names as $name) {
if ($name instanceof Twig_Template) {
return $name;
}
try {
return $this->loadTemplate($name);
} catch (Twig_Error_Loader $e) {
}
}
if (1 === count($names)) {
throw $e;
}
throw new Twig_Error_Loader(sprintf('Unable to find one of the following templates: "%s".', implode('", "', $names)));
}
public function clearTemplateCache()
{
$this->loadedTemplates = array();
}
public function clearCacheFiles()
{
if (false === $this->cache) {
return;
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->cache), RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
if ($file->isFile()) {
@unlink($file->getPathname());
}
}
}
public function getLexer()
{
if (null === $this->lexer) {
$this->lexer = new Twig_Lexer($this);
}
return $this->lexer;
}
public function setLexer(Twig_LexerInterface $lexer)
{
$this->lexer = $lexer;
}
public function tokenize($source, $name = null)
{
return $this->getLexer()->tokenize($source, $name);
}
public function getParser()
{
if (null === $this->parser) {
$this->parser = new Twig_Parser($this);
}
return $this->parser;
}
public function setParser(Twig_ParserInterface $parser)
{
$this->parser = $parser;
}
public function parse(Twig_TokenStream $tokens)
{
return $this->getParser()->parse($tokens);
}
public function getCompiler()
{
if (null === $this->compiler) {
$this->compiler = new Twig_Compiler($this);
}
return $this->compiler;
}
public function setCompiler(Twig_CompilerInterface $compiler)
{
$this->compiler = $compiler;
}
public function compile(Twig_NodeInterface $node)
{
return $this->getCompiler()->compile($node)->getSource();
}
public function compileSource($source, $name = null)
{
try {
return $this->compile($this->parse($this->tokenize($source, $name)));
} catch (Twig_Error $e) {
$e->setTemplateFile($name);
throw $e;
} catch (Exception $e) {
throw new Twig_Error_Runtime(sprintf('An exception has been thrown during the compilation of a template ("%s").', $e->getMessage()), -1, $name, $e);
}
}
public function setLoader(Twig_LoaderInterface $loader)
{
$this->loader = $loader;
}
public function getLoader()
{
if (null === $this->loader) {
throw new LogicException('You must set a loader first.');
}
return $this->loader;
}
public function setCharset($charset)
{
$this->charset = strtoupper($charset);
}
public function getCharset()
{
return $this->charset;
}
public function initRuntime()
{
$this->runtimeInitialized = true;
foreach ($this->getExtensions() as $extension) {
$extension->initRuntime($this);
}
}
public function hasExtension($name)
{
return isset($this->extensions[$name]);
}
public function getExtension($name)
{
if (!isset($this->extensions[$name])) {
throw new Twig_Error_Runtime(sprintf('The "%s" extension is not enabled.', $name));
}
return $this->extensions[$name];
}
public function addExtension(Twig_ExtensionInterface $extension)
{
if ($this->extensionInitialized) {
throw new LogicException(sprintf('Unable to register extension "%s" as extensions have already been initialized.', $extension->getName()));
}
$this->extensions[$extension->getName()] = $extension;
}
public function removeExtension($name)
{
if ($this->extensionInitialized) {
throw new LogicException(sprintf('Unable to remove extension "%s" as extensions have already been initialized.', $name));
}
unset($this->extensions[$name]);
}
public function setExtensions(array $extensions)
{
foreach ($extensions as $extension) {
$this->addExtension($extension);
}
}
public function getExtensions()
{
return $this->extensions;
}
public function addTokenParser(Twig_TokenParserInterface $parser)
{
if ($this->extensionInitialized) {
throw new LogicException('Unable to add a token parser as extensions have already been initialized.');
}
$this->staging->addTokenParser($parser);
}
public function getTokenParsers()
{
if (!$this->extensionInitialized) {
$this->initExtensions();
}
return $this->parsers;
}
public function getTags()
{
$tags = array();
foreach ($this->getTokenParsers()->getParsers() as $parser) {
if ($parser instanceof Twig_TokenParserInterface) {
$tags[$parser->getTag()] = $parser;
}
}
return $tags;
}
public function addNodeVisitor(Twig_NodeVisitorInterface $visitor)
{
if ($this->extensionInitialized) {
throw new LogicException('Unable to add a node visitor as extensions have already been initialized.', $extension->getName());
}
$this->staging->addNodeVisitor($visitor);
}
public function getNodeVisitors()
{
if (!$this->extensionInitialized) {
$this->initExtensions();
}
return $this->visitors;
}
public function addFilter($name, $filter = null)
{
if (!$name instanceof Twig_SimpleFilter && !($filter instanceof Twig_SimpleFilter || $filter instanceof Twig_FilterInterface)) {
throw new LogicException('A filter must be an instance of Twig_FilterInterface or Twig_SimpleFilter');
}
if ($name instanceof Twig_SimpleFilter) {
$filter = $name;
$name = $filter->getName();
}
if ($this->extensionInitialized) {
throw new LogicException(sprintf('Unable to add filter "%s" as extensions have already been initialized.', $name));
}
$this->staging->addFilter($name, $filter);
}
public function getFilter($name)
{
if (!$this->extensionInitialized) {
$this->initExtensions();
}
if (isset($this->filters[$name])) {
return $this->filters[$name];
}
foreach ($this->filters as $pattern => $filter) {
$pattern = str_replace('\\*','(.*?)', preg_quote($pattern,'#'), $count);
if ($count) {
if (preg_match('#^'.$pattern.'$#', $name, $matches)) {
array_shift($matches);
$filter->setArguments($matches);
return $filter;
}
}
}
foreach ($this->filterCallbacks as $callback) {
if (false !== $filter = call_user_func($callback, $name)) {
return $filter;
}
}
return false;
}
public function registerUndefinedFilterCallback($callable)
{
$this->filterCallbacks[] = $callable;
}
public function getFilters()
{
if (!$this->extensionInitialized) {
$this->initExtensions();
}
return $this->filters;
}
public function addTest($name, $test = null)
{
if (!$name instanceof Twig_SimpleTest && !($test instanceof Twig_SimpleTest || $test instanceof Twig_TestInterface)) {
throw new LogicException('A test must be an instance of Twig_TestInterface or Twig_SimpleTest');
}
if ($name instanceof Twig_SimpleTest) {
$test = $name;
$name = $test->getName();
}
if ($this->extensionInitialized) {
throw new LogicException(sprintf('Unable to add test "%s" as extensions have already been initialized.', $name));
}
$this->staging->addTest($name, $test);
}
public function getTests()
{
if (!$this->extensionInitialized) {
$this->initExtensions();
}
return $this->tests;
}
public function getTest($name)
{
if (!$this->extensionInitialized) {
$this->initExtensions();
}
if (isset($this->tests[$name])) {
return $this->tests[$name];
}
return false;
}
public function addFunction($name, $function = null)
{
if (!$name instanceof Twig_SimpleFunction && !($function instanceof Twig_SimpleFunction || $function instanceof Twig_FunctionInterface)) {
throw new LogicException('A function must be an instance of Twig_FunctionInterface or Twig_SimpleFunction');
}
if ($name instanceof Twig_SimpleFunction) {
$function = $name;
$name = $function->getName();
}
if ($this->extensionInitialized) {
throw new LogicException(sprintf('Unable to add function "%s" as extensions have already been initialized.', $name));
}
$this->staging->addFunction($name, $function);
}
public function getFunction($name)
{
if (!$this->extensionInitialized) {
$this->initExtensions();
}
if (isset($this->functions[$name])) {
return $this->functions[$name];
}
foreach ($this->functions as $pattern => $function) {
$pattern = str_replace('\\*','(.*?)', preg_quote($pattern,'#'), $count);
if ($count) {
if (preg_match('#^'.$pattern.'$#', $name, $matches)) {
array_shift($matches);
$function->setArguments($matches);
return $function;
}
}
}
foreach ($this->functionCallbacks as $callback) {
if (false !== $function = call_user_func($callback, $name)) {
return $function;
}
}
return false;
}
public function registerUndefinedFunctionCallback($callable)
{
$this->functionCallbacks[] = $callable;
}
public function getFunctions()
{
if (!$this->extensionInitialized) {
$this->initExtensions();
}
return $this->functions;
}
public function addGlobal($name, $value)
{
if ($this->extensionInitialized || $this->runtimeInitialized) {
if (null === $this->globals) {
$this->globals = $this->initGlobals();
}
}
if ($this->extensionInitialized || $this->runtimeInitialized) {
$this->globals[$name] = $value;
} else {
$this->staging->addGlobal($name, $value);
}
}
public function getGlobals()
{
if (!$this->runtimeInitialized && !$this->extensionInitialized) {
return $this->initGlobals();
}
if (null === $this->globals) {
$this->globals = $this->initGlobals();
}
return $this->globals;
}
public function mergeGlobals(array $context)
{
foreach ($this->getGlobals() as $key => $value) {
if (!array_key_exists($key, $context)) {
$context[$key] = $value;
}
}
return $context;
}
public function getUnaryOperators()
{
if (!$this->extensionInitialized) {
$this->initExtensions();
}
return $this->unaryOperators;
}
public function getBinaryOperators()
{
if (!$this->extensionInitialized) {
$this->initExtensions();
}
return $this->binaryOperators;
}
public function computeAlternatives($name, $items)
{
$alternatives = array();
foreach ($items as $item) {
$lev = levenshtein($name, $item);
if ($lev <= strlen($name) / 3 || false !== strpos($item, $name)) {
$alternatives[$item] = $lev;
}
}
asort($alternatives);
return array_keys($alternatives);
}
protected function initGlobals()
{
$globals = array();
foreach ($this->extensions as $extension) {
$extGlob = $extension->getGlobals();
if (!is_array($extGlob)) {
throw new UnexpectedValueException(sprintf('"%s::getGlobals()" must return an array of globals.', get_class($extension)));
}
$globals[] = $extGlob;
}
$globals[] = $this->staging->getGlobals();
return call_user_func_array('array_merge', $globals);
}
protected function initExtensions()
{
if ($this->extensionInitialized) {
return;
}
$this->extensionInitialized = true;
$this->parsers = new Twig_TokenParserBroker();
$this->filters = array();
$this->functions = array();
$this->tests = array();
$this->visitors = array();
$this->unaryOperators = array();
$this->binaryOperators = array();
foreach ($this->extensions as $extension) {
$this->initExtension($extension);
}
$this->initExtension($this->staging);
}
protected function initExtension(Twig_ExtensionInterface $extension)
{
foreach ($extension->getFilters() as $name => $filter) {
if ($name instanceof Twig_SimpleFilter) {
$filter = $name;
$name = $filter->getName();
} elseif ($filter instanceof Twig_SimpleFilter) {
$name = $filter->getName();
}
$this->filters[$name] = $filter;
}
foreach ($extension->getFunctions() as $name => $function) {
if ($name instanceof Twig_SimpleFunction) {
$function = $name;
$name = $function->getName();
} elseif ($function instanceof Twig_SimpleFunction) {
$name = $function->getName();
}
$this->functions[$name] = $function;
}
foreach ($extension->getTests() as $name => $test) {
if ($name instanceof Twig_SimpleTest) {
$test = $name;
$name = $test->getName();
} elseif ($test instanceof Twig_SimpleTest) {
$name = $test->getName();
}
$this->tests[$name] = $test;
}
foreach ($extension->getTokenParsers() as $parser) {
if ($parser instanceof Twig_TokenParserInterface) {
$this->parsers->addTokenParser($parser);
} elseif ($parser instanceof Twig_TokenParserBrokerInterface) {
$this->parsers->addTokenParserBroker($parser);
} else {
throw new LogicException('getTokenParsers() must return an array of Twig_TokenParserInterface or Twig_TokenParserBrokerInterface instances');
}
}
foreach ($extension->getNodeVisitors() as $visitor) {
$this->visitors[] = $visitor;
}
if ($operators = $extension->getOperators()) {
if (2 !== count($operators)) {
throw new InvalidArgumentException(sprintf('"%s::getOperators()" does not return a valid operators array.', get_class($extension)));
}
$this->unaryOperators = array_merge($this->unaryOperators, $operators[0]);
$this->binaryOperators = array_merge($this->binaryOperators, $operators[1]);
}
}
protected function writeCacheFile($file, $content)
{
$dir = dirname($file);
if (!is_dir($dir)) {
if (false === @mkdir($dir, 0777, true) && !is_dir($dir)) {
throw new RuntimeException(sprintf("Unable to create the cache directory (%s).", $dir));
}
} elseif (!is_writable($dir)) {
throw new RuntimeException(sprintf("Unable to write in the cache directory (%s).", $dir));
}
$tmpFile = tempnam(dirname($file), basename($file));
if (false !== @file_put_contents($tmpFile, $content)) {
if (@rename($tmpFile, $file) || (@copy($tmpFile, $file) && unlink($tmpFile))) {
@chmod($file, 0666 & ~umask());
return;
}
}
throw new RuntimeException(sprintf('Failed to write cache file "%s".', $file));
}
}
}
namespace
{
class Twig_Error extends Exception
{
protected $lineno;
protected $filename;
protected $rawMessage;
protected $previous;
public function __construct($message, $lineno = -1, $filename = null, Exception $previous = null)
{
if (version_compare(PHP_VERSION,'5.3.0','<')) {
$this->previous = $previous;
parent::__construct('');
} else {
parent::__construct('', 0, $previous);
}
$this->lineno = $lineno;
$this->filename = $filename;
if (-1 === $this->lineno || null === $this->filename) {
$this->guessTemplateInfo();
}
$this->rawMessage = $message;
$this->updateRepr();
}
public function getRawMessage()
{
return $this->rawMessage;
}
public function getTemplateFile()
{
return $this->filename;
}
public function setTemplateFile($filename)
{
$this->filename = $filename;
$this->updateRepr();
}
public function getTemplateLine()
{
return $this->lineno;
}
public function setTemplateLine($lineno)
{
$this->lineno = $lineno;
$this->updateRepr();
}
public function guess()
{
$this->guessTemplateInfo();
$this->updateRepr();
}
public function __call($method, $arguments)
{
if ('getprevious'== strtolower($method)) {
return $this->previous;
}
throw new BadMethodCallException(sprintf('Method "Twig_Error::%s()" does not exist.', $method));
}
protected function updateRepr()
{
$this->message = $this->rawMessage;
$dot = false;
if ('.'=== substr($this->message, -1)) {
$this->message = substr($this->message, 0, -1);
$dot = true;
}
if ($this->filename) {
if (is_string($this->filename) || (is_object($this->filename) && method_exists($this->filename,'__toString'))) {
$filename = sprintf('"%s"', $this->filename);
} else {
$filename = json_encode($this->filename);
}
$this->message .= sprintf(' in %s', $filename);
}
if ($this->lineno && $this->lineno >= 0) {
$this->message .= sprintf(' at line %d', $this->lineno);
}
if ($dot) {
$this->message .='.';
}
}
protected function guessTemplateInfo()
{
$template = null;
if (version_compare(phpversion(),'5.3.6','>=')) {
$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT);
} else {
$backtrace = debug_backtrace();
}
foreach ($backtrace as $trace) {
if (isset($trace['object']) && $trace['object'] instanceof Twig_Template &&'Twig_Template'!== get_class($trace['object'])) {
if (null === $this->filename || $this->filename == $trace['object']->getTemplateName()) {
$template = $trace['object'];
}
}
}
if (null !== $template && null === $this->filename) {
$this->filename = $template->getTemplateName();
}
if (null === $template || $this->lineno > -1) {
return;
}
$r = new ReflectionObject($template);
$file = $r->getFileName();
$exceptions = array($e = $this);
while (($e instanceof self || method_exists($e,'getPrevious')) && $e = $e->getPrevious()) {
$exceptions[] = $e;
}
while ($e = array_pop($exceptions)) {
$traces = $e->getTrace();
while ($trace = array_shift($traces)) {
if (!isset($trace['file']) || !isset($trace['line']) || $file != $trace['file']) {
continue;
}
foreach ($template->getDebugInfo() as $codeLine => $templateLine) {
if ($codeLine <= $trace['line']) {
$this->lineno = $templateLine;
return;
}
}
}
}
}
}
}
namespace
{
class Twig_Error_Loader extends Twig_Error
{
public function __construct($message, $lineno = -1, $filename = null, Exception $previous = null)
{
parent::__construct($message, false, false, $previous);
}
}
}
namespace
{
class Twig_Error_Runtime extends Twig_Error
{
}
}
namespace
{
class Twig_Error_Syntax extends Twig_Error
{
}
}
namespace
{
class Twig_ExpressionParser
{
const OPERATOR_LEFT = 1;
const OPERATOR_RIGHT = 2;
protected $parser;
protected $unaryOperators;
protected $binaryOperators;
public function __construct(Twig_Parser $parser, array $unaryOperators, array $binaryOperators)
{
$this->parser = $parser;
$this->unaryOperators = $unaryOperators;
$this->binaryOperators = $binaryOperators;
}
public function parseExpression($precedence = 0)
{
$expr = $this->getPrimary();
$token = $this->parser->getCurrentToken();
while ($this->isBinary($token) && $this->binaryOperators[$token->getValue()]['precedence'] >= $precedence) {
$op = $this->binaryOperators[$token->getValue()];
$this->parser->getStream()->next();
if (isset($op['callable'])) {
$expr = call_user_func($op['callable'], $this->parser, $expr);
} else {
$expr1 = $this->parseExpression(self::OPERATOR_LEFT === $op['associativity'] ? $op['precedence'] + 1 : $op['precedence']);
$class = $op['class'];
$expr = new $class($expr, $expr1, $token->getLine());
}
$token = $this->parser->getCurrentToken();
}
if (0 === $precedence) {
return $this->parseConditionalExpression($expr);
}
return $expr;
}
protected function getPrimary()
{
$token = $this->parser->getCurrentToken();
if ($this->isUnary($token)) {
$operator = $this->unaryOperators[$token->getValue()];
$this->parser->getStream()->next();
$expr = $this->parseExpression($operator['precedence']);
$class = $operator['class'];
return $this->parsePostfixExpression(new $class($expr, $token->getLine()));
} elseif ($token->test(Twig_Token::PUNCTUATION_TYPE,'(')) {
$this->parser->getStream()->next();
$expr = $this->parseExpression();
$this->parser->getStream()->expect(Twig_Token::PUNCTUATION_TYPE,')','An opened parenthesis is not properly closed');
return $this->parsePostfixExpression($expr);
}
return $this->parsePrimaryExpression();
}
protected function parseConditionalExpression($expr)
{
while ($this->parser->getStream()->test(Twig_Token::PUNCTUATION_TYPE,'?')) {
$this->parser->getStream()->next();
if (!$this->parser->getStream()->test(Twig_Token::PUNCTUATION_TYPE,':')) {
$expr2 = $this->parseExpression();
if ($this->parser->getStream()->test(Twig_Token::PUNCTUATION_TYPE,':')) {
$this->parser->getStream()->next();
$expr3 = $this->parseExpression();
} else {
$expr3 = new Twig_Node_Expression_Constant('', $this->parser->getCurrentToken()->getLine());
}
} else {
$this->parser->getStream()->next();
$expr2 = $expr;
$expr3 = $this->parseExpression();
}
$expr = new Twig_Node_Expression_Conditional($expr, $expr2, $expr3, $this->parser->getCurrentToken()->getLine());
}
return $expr;
}
protected function isUnary(Twig_Token $token)
{
return $token->test(Twig_Token::OPERATOR_TYPE) && isset($this->unaryOperators[$token->getValue()]);
}
protected function isBinary(Twig_Token $token)
{
return $token->test(Twig_Token::OPERATOR_TYPE) && isset($this->binaryOperators[$token->getValue()]);
}
public function parsePrimaryExpression()
{
$token = $this->parser->getCurrentToken();
switch ($token->getType()) {
case Twig_Token::NAME_TYPE:
$this->parser->getStream()->next();
switch ($token->getValue()) {
case'true':
case'TRUE':
$node = new Twig_Node_Expression_Constant(true, $token->getLine());
break;
case'false':
case'FALSE':
$node = new Twig_Node_Expression_Constant(false, $token->getLine());
break;
case'none':
case'NONE':
case'null':
case'NULL':
$node = new Twig_Node_Expression_Constant(null, $token->getLine());
break;
default:
if ('('=== $this->parser->getCurrentToken()->getValue()) {
$node = $this->getFunctionNode($token->getValue(), $token->getLine());
} else {
$node = new Twig_Node_Expression_Name($token->getValue(), $token->getLine());
}
}
break;
case Twig_Token::NUMBER_TYPE:
$this->parser->getStream()->next();
$node = new Twig_Node_Expression_Constant($token->getValue(), $token->getLine());
break;
case Twig_Token::STRING_TYPE:
case Twig_Token::INTERPOLATION_START_TYPE:
$node = $this->parseStringExpression();
break;
default:
if ($token->test(Twig_Token::PUNCTUATION_TYPE,'[')) {
$node = $this->parseArrayExpression();
} elseif ($token->test(Twig_Token::PUNCTUATION_TYPE,'{')) {
$node = $this->parseHashExpression();
} else {
throw new Twig_Error_Syntax(sprintf('Unexpected token "%s" of value "%s"', Twig_Token::typeToEnglish($token->getType(), $token->getLine()), $token->getValue()), $token->getLine(), $this->parser->getFilename());
}
}
return $this->parsePostfixExpression($node);
}
public function parseStringExpression()
{
$stream = $this->parser->getStream();
$nodes = array();
$nextCanBeString = true;
while (true) {
if ($stream->test(Twig_Token::STRING_TYPE) && $nextCanBeString) {
$token = $stream->next();
$nodes[] = new Twig_Node_Expression_Constant($token->getValue(), $token->getLine());
$nextCanBeString = false;
} elseif ($stream->test(Twig_Token::INTERPOLATION_START_TYPE)) {
$stream->next();
$nodes[] = $this->parseExpression();
$stream->expect(Twig_Token::INTERPOLATION_END_TYPE);
$nextCanBeString = true;
} else {
break;
}
}
$expr = array_shift($nodes);
foreach ($nodes as $node) {
$expr = new Twig_Node_Expression_Binary_Concat($expr, $node, $node->getLine());
}
return $expr;
}
public function parseArrayExpression()
{
$stream = $this->parser->getStream();
$stream->expect(Twig_Token::PUNCTUATION_TYPE,'[','An array element was expected');
$node = new Twig_Node_Expression_Array(array(), $stream->getCurrent()->getLine());
$first = true;
while (!$stream->test(Twig_Token::PUNCTUATION_TYPE,']')) {
if (!$first) {
$stream->expect(Twig_Token::PUNCTUATION_TYPE,',','An array element must be followed by a comma');
if ($stream->test(Twig_Token::PUNCTUATION_TYPE,']')) {
break;
}
}
$first = false;
$node->addElement($this->parseExpression());
}
$stream->expect(Twig_Token::PUNCTUATION_TYPE,']','An opened array is not properly closed');
return $node;
}
public function parseHashExpression()
{
$stream = $this->parser->getStream();
$stream->expect(Twig_Token::PUNCTUATION_TYPE,'{','A hash element was expected');
$node = new Twig_Node_Expression_Array(array(), $stream->getCurrent()->getLine());
$first = true;
while (!$stream->test(Twig_Token::PUNCTUATION_TYPE,'}')) {
if (!$first) {
$stream->expect(Twig_Token::PUNCTUATION_TYPE,',','A hash value must be followed by a comma');
if ($stream->test(Twig_Token::PUNCTUATION_TYPE,'}')) {
break;
}
}
$first = false;
if ($stream->test(Twig_Token::STRING_TYPE) || $stream->test(Twig_Token::NAME_TYPE) || $stream->test(Twig_Token::NUMBER_TYPE)) {
$token = $stream->next();
$key = new Twig_Node_Expression_Constant($token->getValue(), $token->getLine());
} elseif ($stream->test(Twig_Token::PUNCTUATION_TYPE,'(')) {
$key = $this->parseExpression();
} else {
$current = $stream->getCurrent();
throw new Twig_Error_Syntax(sprintf('A hash key must be a quoted string, a number, a name, or an expression enclosed in parentheses (unexpected token "%s" of value "%s"', Twig_Token::typeToEnglish($current->getType(), $current->getLine()), $current->getValue()), $current->getLine(), $this->parser->getFilename());
}
$stream->expect(Twig_Token::PUNCTUATION_TYPE,':','A hash key must be followed by a colon (:)');
$value = $this->parseExpression();
$node->addElement($value, $key);
}
$stream->expect(Twig_Token::PUNCTUATION_TYPE,'}','An opened hash is not properly closed');
return $node;
}
public function parsePostfixExpression($node)
{
while (true) {
$token = $this->parser->getCurrentToken();
if ($token->getType() == Twig_Token::PUNCTUATION_TYPE) {
if ('.'== $token->getValue() ||'['== $token->getValue()) {
$node = $this->parseSubscriptExpression($node);
} elseif ('|'== $token->getValue()) {
$node = $this->parseFilterExpression($node);
} else {
break;
}
} else {
break;
}
}
return $node;
}
public function getFunctionNode($name, $line)
{
switch ($name) {
case'parent':
$args = $this->parseArguments();
if (!count($this->parser->getBlockStack())) {
throw new Twig_Error_Syntax('Calling "parent" outside a block is forbidden', $line, $this->parser->getFilename());
}
if (!$this->parser->getParent() && !$this->parser->hasTraits()) {
throw new Twig_Error_Syntax('Calling "parent" on a template that does not extend nor "use" another template is forbidden', $line, $this->parser->getFilename());
}
return new Twig_Node_Expression_Parent($this->parser->peekBlockStack(), $line);
case'block':
return new Twig_Node_Expression_BlockReference($this->parseArguments()->getNode(0), false, $line);
case'attribute':
$args = $this->parseArguments();
if (count($args) < 2) {
throw new Twig_Error_Syntax('The "attribute" function takes at least two arguments (the variable and the attributes)', $line, $this->parser->getFilename());
}
return new Twig_Node_Expression_GetAttr($args->getNode(0), $args->getNode(1), count($args) > 2 ? $args->getNode(2) : new Twig_Node_Expression_Array(array(), $line), Twig_TemplateInterface::ANY_CALL, $line);
default:
if (null !== $alias = $this->parser->getImportedSymbol('function', $name)) {
$arguments = new Twig_Node_Expression_Array(array(), $line);
foreach ($this->parseArguments() as $n) {
$arguments->addElement($n);
}
$node = new Twig_Node_Expression_MethodCall($alias['node'], $alias['name'], $arguments, $line);
$node->setAttribute('safe', true);
return $node;
}
$args = $this->parseArguments(true);
$class = $this->getFunctionNodeClass($name, $line);
return new $class($name, $args, $line);
}
}
public function parseSubscriptExpression($node)
{
$stream = $this->parser->getStream();
$token = $stream->next();
$lineno = $token->getLine();
$arguments = new Twig_Node_Expression_Array(array(), $lineno);
$type = Twig_TemplateInterface::ANY_CALL;
if ($token->getValue() =='.') {
$token = $stream->next();
if (
$token->getType() == Twig_Token::NAME_TYPE
||
$token->getType() == Twig_Token::NUMBER_TYPE
||
($token->getType() == Twig_Token::OPERATOR_TYPE && preg_match(Twig_Lexer::REGEX_NAME, $token->getValue()))
) {
$arg = new Twig_Node_Expression_Constant($token->getValue(), $lineno);
if ($stream->test(Twig_Token::PUNCTUATION_TYPE,'(')) {
$type = Twig_TemplateInterface::METHOD_CALL;
foreach ($this->parseArguments() as $n) {
$arguments->addElement($n);
}
}
} else {
throw new Twig_Error_Syntax('Expected name or number', $lineno, $this->parser->getFilename());
}
if ($node instanceof Twig_Node_Expression_Name && null !== $this->parser->getImportedSymbol('template', $node->getAttribute('name'))) {
if (!$arg instanceof Twig_Node_Expression_Constant) {
throw new Twig_Error_Syntax(sprintf('Dynamic macro names are not supported (called on "%s")', $node->getAttribute('name')), $token->getLine(), $this->parser->getFilename());
}
$node = new Twig_Node_Expression_MethodCall($node,'get'.$arg->getAttribute('value'), $arguments, $lineno);
$node->setAttribute('safe', true);
return $node;
}
} else {
$type = Twig_TemplateInterface::ARRAY_CALL;
$slice = false;
if ($stream->test(Twig_Token::PUNCTUATION_TYPE,':')) {
$slice = true;
$arg = new Twig_Node_Expression_Constant(0, $token->getLine());
} else {
$arg = $this->parseExpression();
}
if ($stream->test(Twig_Token::PUNCTUATION_TYPE,':')) {
$slice = true;
$stream->next();
}
if ($slice) {
if ($stream->test(Twig_Token::PUNCTUATION_TYPE,']')) {
$length = new Twig_Node_Expression_Constant(null, $token->getLine());
} else {
$length = $this->parseExpression();
}
$class = $this->getFilterNodeClass('slice', $token->getLine());
$arguments = new Twig_Node(array($arg, $length));
$filter = new $class($node, new Twig_Node_Expression_Constant('slice', $token->getLine()), $arguments, $token->getLine());
$stream->expect(Twig_Token::PUNCTUATION_TYPE,']');
return $filter;
}
$stream->expect(Twig_Token::PUNCTUATION_TYPE,']');
}
return new Twig_Node_Expression_GetAttr($node, $arg, $arguments, $type, $lineno);
}
public function parseFilterExpression($node)
{
$this->parser->getStream()->next();
return $this->parseFilterExpressionRaw($node);
}
public function parseFilterExpressionRaw($node, $tag = null)
{
while (true) {
$token = $this->parser->getStream()->expect(Twig_Token::NAME_TYPE);
$name = new Twig_Node_Expression_Constant($token->getValue(), $token->getLine());
if (!$this->parser->getStream()->test(Twig_Token::PUNCTUATION_TYPE,'(')) {
$arguments = new Twig_Node();
} else {
$arguments = $this->parseArguments(true);
}
$class = $this->getFilterNodeClass($name->getAttribute('value'), $token->getLine());
$node = new $class($node, $name, $arguments, $token->getLine(), $tag);
if (!$this->parser->getStream()->test(Twig_Token::PUNCTUATION_TYPE,'|')) {
break;
}
$this->parser->getStream()->next();
}
return $node;
}
public function parseArguments($namedArguments = false, $definition = false)
{
$args = array();
$stream = $this->parser->getStream();
$stream->expect(Twig_Token::PUNCTUATION_TYPE,'(','A list of arguments must begin with an opening parenthesis');
while (!$stream->test(Twig_Token::PUNCTUATION_TYPE,')')) {
if (!empty($args)) {
$stream->expect(Twig_Token::PUNCTUATION_TYPE,',','Arguments must be separated by a comma');
}
if ($definition) {
$token = $stream->expect(Twig_Token::NAME_TYPE, null,'An argument must be a name');
$value = new Twig_Node_Expression_Name($token->getValue(), $this->parser->getCurrentToken()->getLine());
} else {
$value = $this->parseExpression();
}
$name = null;
if ($namedArguments && $stream->test(Twig_Token::OPERATOR_TYPE,'=')) {
$token = $stream->next();
if (!$value instanceof Twig_Node_Expression_Name) {
throw new Twig_Error_Syntax(sprintf('A parameter name must be a string, "%s" given', get_class($value)), $token->getLine(), $this->parser->getFilename());
}
$name = $value->getAttribute('name');
if ($definition) {
$value = $this->parsePrimaryExpression();
if (!$this->checkConstantExpression($value)) {
throw new Twig_Error_Syntax(sprintf('A default value for an argument must be a constant (a boolean, a string, a number, or an array).'), $token->getLine(), $this->parser->getFilename());
}
} else {
$value = $this->parseExpression();
}
}
if ($definition) {
if (null === $name) {
$name = $value->getAttribute('name');
$value = new Twig_Node_Expression_Constant(null, $this->parser->getCurrentToken()->getLine());
}
$args[$name] = $value;
} else {
if (null === $name) {
$args[] = $value;
} else {
$args[$name] = $value;
}
}
}
$stream->expect(Twig_Token::PUNCTUATION_TYPE,')','A list of arguments must be closed by a parenthesis');
return new Twig_Node($args);
}
public function parseAssignmentExpression()
{
$targets = array();
while (true) {
$token = $this->parser->getStream()->expect(Twig_Token::NAME_TYPE, null,'Only variables can be assigned to');
if (in_array($token->getValue(), array('true','false','none'))) {
throw new Twig_Error_Syntax(sprintf('You cannot assign a value to "%s"', $token->getValue()), $token->getLine(), $this->parser->getFilename());
}
$targets[] = new Twig_Node_Expression_AssignName($token->getValue(), $token->getLine());
if (!$this->parser->getStream()->test(Twig_Token::PUNCTUATION_TYPE,',')) {
break;
}
$this->parser->getStream()->next();
}
return new Twig_Node($targets);
}
public function parseMultitargetExpression()
{
$targets = array();
while (true) {
$targets[] = $this->parseExpression();
if (!$this->parser->getStream()->test(Twig_Token::PUNCTUATION_TYPE,',')) {
break;
}
$this->parser->getStream()->next();
}
return new Twig_Node($targets);
}
protected function getFunctionNodeClass($name, $line)
{
$env = $this->parser->getEnvironment();
if (false === $function = $env->getFunction($name)) {
$message = sprintf('The function "%s" does not exist', $name);
if ($alternatives = $env->computeAlternatives($name, array_keys($env->getFunctions()))) {
$message = sprintf('%s. Did you mean "%s"', $message, implode('", "', $alternatives));
}
throw new Twig_Error_Syntax($message, $line, $this->parser->getFilename());
}
if ($function instanceof Twig_SimpleFunction) {
return $function->getNodeClass();
}
return $function instanceof Twig_Function_Node ? $function->getClass() :'Twig_Node_Expression_Function';
}
protected function getFilterNodeClass($name, $line)
{
$env = $this->parser->getEnvironment();
if (false === $filter = $env->getFilter($name)) {
$message = sprintf('The filter "%s" does not exist', $name);
if ($alternatives = $env->computeAlternatives($name, array_keys($env->getFilters()))) {
$message = sprintf('%s. Did you mean "%s"', $message, implode('", "', $alternatives));
}
throw new Twig_Error_Syntax($message, $line, $this->parser->getFilename());
}
if ($filter instanceof Twig_SimpleFilter) {
return $filter->getNodeClass();
}
return $filter instanceof Twig_Filter_Node ? $filter->getClass() :'Twig_Node_Expression_Filter';
}
protected function checkConstantExpression(Twig_NodeInterface $node)
{
if (!($node instanceof Twig_Node_Expression_Constant || $node instanceof Twig_Node_Expression_Array)) {
return false;
}
foreach ($node as $n) {
if (!$this->checkConstantExpression($n)) {
return false;
}
}
return true;
}
}
}
namespace
{
interface Twig_ExtensionInterface
{
public function initRuntime(Twig_Environment $environment);
public function getTokenParsers();
public function getNodeVisitors();
public function getFilters();
public function getTests();
public function getFunctions();
public function getOperators();
public function getGlobals();
public function getName();
}
}
namespace
{
abstract class Twig_Extension implements Twig_ExtensionInterface
{
public function initRuntime(Twig_Environment $environment)
{
}
public function getTokenParsers()
{
return array();
}
public function getNodeVisitors()
{
return array();
}
public function getFilters()
{
return array();
}
public function getTests()
{
return array();
}
public function getFunctions()
{
return array();
}
public function getOperators()
{
return array();
}
public function getGlobals()
{
return array();
}
}
}
namespace
{
if (!defined('ENT_SUBSTITUTE')) {
define('ENT_SUBSTITUTE', 8);
}
class Twig_Extension_Core extends Twig_Extension
{
protected $dateFormats = array('F j, Y H:i','%d days');
protected $numberFormat = array(0,'.',',');
protected $timezone = null;
public function setDateFormat($format = null, $dateIntervalFormat = null)
{
if (null !== $format) {
$this->dateFormats[0] = $format;
}
if (null !== $dateIntervalFormat) {
$this->dateFormats[1] = $dateIntervalFormat;
}
}
public function getDateFormat()
{
return $this->dateFormats;
}
public function setTimezone($timezone)
{
$this->timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
}
public function getTimezone()
{
if (null === $this->timezone) {
$this->timezone = new DateTimeZone(date_default_timezone_get());
}
return $this->timezone;
}
public function setNumberFormat($decimal, $decimalPoint, $thousandSep)
{
$this->numberFormat = array($decimal, $decimalPoint, $thousandSep);
}
public function getNumberFormat()
{
return $this->numberFormat;
}
public function getTokenParsers()
{
return array(
new Twig_TokenParser_For(),
new Twig_TokenParser_If(),
new Twig_TokenParser_Extends(),
new Twig_TokenParser_Include(),
new Twig_TokenParser_Block(),
new Twig_TokenParser_Use(),
new Twig_TokenParser_Filter(),
new Twig_TokenParser_Macro(),
new Twig_TokenParser_Import(),
new Twig_TokenParser_From(),
new Twig_TokenParser_Set(),
new Twig_TokenParser_Spaceless(),
new Twig_TokenParser_Flush(),
new Twig_TokenParser_Do(),
new Twig_TokenParser_Embed(),
);
}
public function getFilters()
{
$filters = array(
new Twig_SimpleFilter('date','twig_date_format_filter', array('needs_environment'=> true)),
new Twig_SimpleFilter('date_modify','twig_date_modify_filter', array('needs_environment'=> true)),
new Twig_SimpleFilter('format','sprintf'),
new Twig_SimpleFilter('replace','strtr'),
new Twig_SimpleFilter('number_format','twig_number_format_filter', array('needs_environment'=> true)),
new Twig_SimpleFilter('abs','abs'),
new Twig_SimpleFilter('url_encode','twig_urlencode_filter'),
new Twig_SimpleFilter('json_encode','twig_jsonencode_filter'),
new Twig_SimpleFilter('convert_encoding','twig_convert_encoding'),
new Twig_SimpleFilter('title','twig_title_string_filter', array('needs_environment'=> true)),
new Twig_SimpleFilter('capitalize','twig_capitalize_string_filter', array('needs_environment'=> true)),
new Twig_SimpleFilter('upper','strtoupper'),
new Twig_SimpleFilter('lower','strtolower'),
new Twig_SimpleFilter('striptags','strip_tags'),
new Twig_SimpleFilter('trim','trim'),
new Twig_SimpleFilter('nl2br','nl2br', array('pre_escape'=>'html','is_safe'=> array('html'))),
new Twig_SimpleFilter('join','twig_join_filter'),
new Twig_SimpleFilter('split','twig_split_filter'),
new Twig_SimpleFilter('sort','twig_sort_filter'),
new Twig_SimpleFilter('merge','twig_array_merge'),
new Twig_SimpleFilter('batch','twig_array_batch'),
new Twig_SimpleFilter('reverse','twig_reverse_filter', array('needs_environment'=> true)),
new Twig_SimpleFilter('length','twig_length_filter', array('needs_environment'=> true)),
new Twig_SimpleFilter('slice','twig_slice', array('needs_environment'=> true)),
new Twig_SimpleFilter('first','twig_first', array('needs_environment'=> true)),
new Twig_SimpleFilter('last','twig_last', array('needs_environment'=> true)),
new Twig_SimpleFilter('default','_twig_default_filter', array('node_class'=>'Twig_Node_Expression_Filter_Default')),
new Twig_SimpleFilter('keys','twig_get_array_keys_filter'),
new Twig_SimpleFilter('escape','twig_escape_filter', array('needs_environment'=> true,'is_safe_callback'=>'twig_escape_filter_is_safe')),
new Twig_SimpleFilter('e','twig_escape_filter', array('needs_environment'=> true,'is_safe_callback'=>'twig_escape_filter_is_safe')),
);
if (function_exists('mb_get_info')) {
$filters[] = new Twig_SimpleFilter('upper','twig_upper_filter', array('needs_environment'=> true));
$filters[] = new Twig_SimpleFilter('lower','twig_lower_filter', array('needs_environment'=> true));
}
return $filters;
}
public function getFunctions()
{
return array(
new Twig_SimpleFunction('range','range'),
new Twig_SimpleFunction('constant','twig_constant'),
new Twig_SimpleFunction('cycle','twig_cycle'),
new Twig_SimpleFunction('random','twig_random', array('needs_environment'=> true)),
new Twig_SimpleFunction('date','twig_date_converter', array('needs_environment'=> true)),
new Twig_SimpleFunction('include','twig_include', array('needs_environment'=> true,'needs_context'=> true,'is_safe'=> array('all'))),
);
}
public function getTests()
{
return array(
new Twig_SimpleTest('even', null, array('node_class'=>'Twig_Node_Expression_Test_Even')),
new Twig_SimpleTest('odd', null, array('node_class'=>'Twig_Node_Expression_Test_Odd')),
new Twig_SimpleTest('defined', null, array('node_class'=>'Twig_Node_Expression_Test_Defined')),
new Twig_SimpleTest('sameas', null, array('node_class'=>'Twig_Node_Expression_Test_Sameas')),
new Twig_SimpleTest('none', null, array('node_class'=>'Twig_Node_Expression_Test_Null')),
new Twig_SimpleTest('null', null, array('node_class'=>'Twig_Node_Expression_Test_Null')),
new Twig_SimpleTest('divisibleby', null, array('node_class'=>'Twig_Node_Expression_Test_Divisibleby')),
new Twig_SimpleTest('constant', null, array('node_class'=>'Twig_Node_Expression_Test_Constant')),
new Twig_SimpleTest('empty','twig_test_empty'),
new Twig_SimpleTest('iterable','twig_test_iterable'),
);
}
public function getOperators()
{
return array(
array('not'=> array('precedence'=> 50,'class'=>'Twig_Node_Expression_Unary_Not'),'-'=> array('precedence'=> 500,'class'=>'Twig_Node_Expression_Unary_Neg'),'+'=> array('precedence'=> 500,'class'=>'Twig_Node_Expression_Unary_Pos'),
),
array('or'=> array('precedence'=> 10,'class'=>'Twig_Node_Expression_Binary_Or','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'and'=> array('precedence'=> 15,'class'=>'Twig_Node_Expression_Binary_And','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'b-or'=> array('precedence'=> 16,'class'=>'Twig_Node_Expression_Binary_BitwiseOr','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'b-xor'=> array('precedence'=> 17,'class'=>'Twig_Node_Expression_Binary_BitwiseXor','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'b-and'=> array('precedence'=> 18,'class'=>'Twig_Node_Expression_Binary_BitwiseAnd','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'=='=> array('precedence'=> 20,'class'=>'Twig_Node_Expression_Binary_Equal','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'!='=> array('precedence'=> 20,'class'=>'Twig_Node_Expression_Binary_NotEqual','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'<'=> array('precedence'=> 20,'class'=>'Twig_Node_Expression_Binary_Less','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'>'=> array('precedence'=> 20,'class'=>'Twig_Node_Expression_Binary_Greater','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'>='=> array('precedence'=> 20,'class'=>'Twig_Node_Expression_Binary_GreaterEqual','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'<='=> array('precedence'=> 20,'class'=>'Twig_Node_Expression_Binary_LessEqual','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'not in'=> array('precedence'=> 20,'class'=>'Twig_Node_Expression_Binary_NotIn','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'in'=> array('precedence'=> 20,'class'=>'Twig_Node_Expression_Binary_In','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'..'=> array('precedence'=> 25,'class'=>'Twig_Node_Expression_Binary_Range','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'+'=> array('precedence'=> 30,'class'=>'Twig_Node_Expression_Binary_Add','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'-'=> array('precedence'=> 30,'class'=>'Twig_Node_Expression_Binary_Sub','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'~'=> array('precedence'=> 40,'class'=>'Twig_Node_Expression_Binary_Concat','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'*'=> array('precedence'=> 60,'class'=>'Twig_Node_Expression_Binary_Mul','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'/'=> array('precedence'=> 60,'class'=>'Twig_Node_Expression_Binary_Div','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'//'=> array('precedence'=> 60,'class'=>'Twig_Node_Expression_Binary_FloorDiv','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'%'=> array('precedence'=> 60,'class'=>'Twig_Node_Expression_Binary_Mod','associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'is'=> array('precedence'=> 100,'callable'=> array($this,'parseTestExpression'),'associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'is not'=> array('precedence'=> 100,'callable'=> array($this,'parseNotTestExpression'),'associativity'=> Twig_ExpressionParser::OPERATOR_LEFT),'**'=> array('precedence'=> 200,'class'=>'Twig_Node_Expression_Binary_Power','associativity'=> Twig_ExpressionParser::OPERATOR_RIGHT),
),
);
}
public function parseNotTestExpression(Twig_Parser $parser, $node)
{
return new Twig_Node_Expression_Unary_Not($this->parseTestExpression($parser, $node), $parser->getCurrentToken()->getLine());
}
public function parseTestExpression(Twig_Parser $parser, $node)
{
$stream = $parser->getStream();
$name = $stream->expect(Twig_Token::NAME_TYPE)->getValue();
$arguments = null;
if ($stream->test(Twig_Token::PUNCTUATION_TYPE,'(')) {
$arguments = $parser->getExpressionParser()->parseArguments(true);
}
$class = $this->getTestNodeClass($parser, $name, $node->getLine());
return new $class($node, $name, $arguments, $parser->getCurrentToken()->getLine());
}
protected function getTestNodeClass(Twig_Parser $parser, $name, $line)
{
$env = $parser->getEnvironment();
$testMap = $env->getTests();
if (!isset($testMap[$name])) {
$message = sprintf('The test "%s" does not exist', $name);
if ($alternatives = $env->computeAlternatives($name, array_keys($env->getTests()))) {
$message = sprintf('%s. Did you mean "%s"', $message, implode('", "', $alternatives));
}
throw new Twig_Error_Syntax($message, $line, $parser->getFilename());
}
if ($testMap[$name] instanceof Twig_SimpleTest) {
return $testMap[$name]->getNodeClass();
}
return $testMap[$name] instanceof Twig_Test_Node ? $testMap[$name]->getClass() :'Twig_Node_Expression_Test';
}
public function getName()
{
return'core';
}
}
function twig_cycle($values, $position)
{
if (!is_array($values) && !$values instanceof ArrayAccess) {
return $values;
}
return $values[$position % count($values)];
}
function twig_random(Twig_Environment $env, $values = null)
{
if (null === $values) {
return mt_rand();
}
if (is_int($values) || is_float($values)) {
return $values < 0 ? mt_rand($values, 0) : mt_rand(0, $values);
}
if ($values instanceof Traversable) {
$values = iterator_to_array($values);
} elseif (is_string($values)) {
if (''=== $values) {
return'';
}
if (null !== $charset = $env->getCharset()) {
if ('UTF-8'!= $charset) {
$values = twig_convert_encoding($values,'UTF-8', $charset);
}
$values = preg_split('/(?<!^)(?!$)/u', $values);
if ('UTF-8'!= $charset) {
foreach ($values as $i => $value) {
$values[$i] = twig_convert_encoding($value, $charset,'UTF-8');
}
}
} else {
return $values[mt_rand(0, strlen($values) - 1)];
}
}
if (!is_array($values)) {
return $values;
}
if (0 === count($values)) {
throw new Twig_Error_Runtime('The random function cannot pick from an empty array.');
}
return $values[array_rand($values, 1)];
}
function twig_date_format_filter(Twig_Environment $env, $date, $format = null, $timezone = null)
{
if (null === $format) {
$formats = $env->getExtension('core')->getDateFormat();
$format = $date instanceof DateInterval ? $formats[1] : $formats[0];
}
if ($date instanceof DateInterval) {
return $date->format($format);
}
return twig_date_converter($env, $date, $timezone)->format($format);
}
function twig_date_modify_filter(Twig_Environment $env, $date, $modifier)
{
$date = twig_date_converter($env, $date, false);
$date->modify($modifier);
return $date;
}
function twig_date_converter(Twig_Environment $env, $date = null, $timezone = null)
{
if (!$timezone) {
$defaultTimezone = $env->getExtension('core')->getTimezone();
} elseif (!$timezone instanceof DateTimeZone) {
$defaultTimezone = new DateTimeZone($timezone);
} else {
$defaultTimezone = $timezone;
}
if ($date instanceof DateTime) {
$date = clone $date;
if (false !== $timezone) {
$date->setTimezone($defaultTimezone);
}
return $date;
}
$asString = (string) $date;
if (ctype_digit($asString) || (!empty($asString) &&'-'=== $asString[0] && ctype_digit(substr($asString, 1)))) {
$date ='@'.$date;
}
$date = new DateTime($date, $defaultTimezone);
if (false !== $timezone) {
$date->setTimezone($defaultTimezone);
}
return $date;
}
function twig_number_format_filter(Twig_Environment $env, $number, $decimal = null, $decimalPoint = null, $thousandSep = null)
{
$defaults = $env->getExtension('core')->getNumberFormat();
if (null === $decimal) {
$decimal = $defaults[0];
}
if (null === $decimalPoint) {
$decimalPoint = $defaults[1];
}
if (null === $thousandSep) {
$thousandSep = $defaults[2];
}
return number_format((float) $number, $decimal, $decimalPoint, $thousandSep);
}
function twig_urlencode_filter($url, $raw = false)
{
if (is_array($url)) {
return http_build_query($url,'','&');
}
if ($raw) {
return rawurlencode($url);
}
return urlencode($url);
}
if (version_compare(PHP_VERSION,'5.3.0','<')) {
function twig_jsonencode_filter($value, $options = 0)
{
if ($value instanceof Twig_Markup) {
$value = (string) $value;
} elseif (is_array($value)) {
array_walk_recursive($value,'_twig_markup2string');
}
return json_encode($value);
}
} else {
function twig_jsonencode_filter($value, $options = 0)
{
if ($value instanceof Twig_Markup) {
$value = (string) $value;
} elseif (is_array($value)) {
array_walk_recursive($value,'_twig_markup2string');
}
return json_encode($value, $options);
}
}
function _twig_markup2string(&$value)
{
if ($value instanceof Twig_Markup) {
$value = (string) $value;
}
}
function twig_array_merge($arr1, $arr2)
{
if (!is_array($arr1) || !is_array($arr2)) {
throw new Twig_Error_Runtime('The merge filter only works with arrays or hashes.');
}
return array_merge($arr1, $arr2);
}
function twig_slice(Twig_Environment $env, $item, $start, $length = null, $preserveKeys = false)
{
if ($item instanceof Traversable) {
$item = iterator_to_array($item, false);
}
if (is_array($item)) {
return array_slice($item, $start, $length, $preserveKeys);
}
$item = (string) $item;
if (function_exists('mb_get_info') && null !== $charset = $env->getCharset()) {
return mb_substr($item, $start, null === $length ? mb_strlen($item, $charset) - $start : $length, $charset);
}
return null === $length ? substr($item, $start) : substr($item, $start, $length);
}
function twig_first(Twig_Environment $env, $item)
{
$elements = twig_slice($env, $item, 0, 1, false);
return is_string($elements) ? $elements[0] : current($elements);
}
function twig_last(Twig_Environment $env, $item)
{
$elements = twig_slice($env, $item, -1, 1, false);
return is_string($elements) ? $elements[0] : current($elements);
}
function twig_join_filter($value, $glue ='')
{
if ($value instanceof Traversable) {
$value = iterator_to_array($value, false);
}
return implode($glue, (array) $value);
}
function twig_split_filter($value, $delimiter, $limit = null)
{
if (empty($delimiter)) {
return str_split($value, null === $limit ? 1 : $limit);
}
return null === $limit ? explode($delimiter, $value) : explode($delimiter, $value, $limit);
}
function _twig_default_filter($value, $default ='')
{
if (twig_test_empty($value)) {
return $default;
}
return $value;
}
function twig_get_array_keys_filter($array)
{
if (is_object($array) && $array instanceof Traversable) {
return array_keys(iterator_to_array($array));
}
if (!is_array($array)) {
return array();
}
return array_keys($array);
}
function twig_reverse_filter(Twig_Environment $env, $item, $preserveKeys = false)
{
if (is_object($item) && $item instanceof Traversable) {
return array_reverse(iterator_to_array($item), $preserveKeys);
}
if (is_array($item)) {
return array_reverse($item, $preserveKeys);
}
if (null !== $charset = $env->getCharset()) {
$string = (string) $item;
if ('UTF-8'!= $charset) {
$item = twig_convert_encoding($string,'UTF-8', $charset);
}
preg_match_all('/./us', $item, $matches);
$string = implode('', array_reverse($matches[0]));
if ('UTF-8'!= $charset) {
$string = twig_convert_encoding($string, $charset,'UTF-8');
}
return $string;
}
return strrev((string) $item);
}
function twig_sort_filter($array)
{
asort($array);
return $array;
}
function twig_in_filter($value, $compare)
{
if (is_array($compare)) {
return in_array($value, $compare, is_object($value));
} elseif (is_string($compare)) {
if (!strlen($value)) {
return empty($compare);
}
return false !== strpos($compare, (string) $value);
} elseif ($compare instanceof Traversable) {
return in_array($value, iterator_to_array($compare, false), is_object($value));
}
return false;
}
function twig_escape_filter(Twig_Environment $env, $string, $strategy ='html', $charset = null, $autoescape = false)
{
if ($autoescape && $string instanceof Twig_Markup) {
return $string;
}
if (!is_string($string)) {
if (is_object($string) && method_exists($string,'__toString')) {
$string = (string) $string;
} else {
return $string;
}
}
if (null === $charset) {
$charset = $env->getCharset();
}
switch ($strategy) {
case'html':
static $htmlspecialcharsCharsets = array('ISO-8859-1'=> true,'ISO8859-1'=> true,'ISO-8859-15'=> true,'ISO8859-15'=> true,'utf-8'=> true,'UTF-8'=> true,'CP866'=> true,'IBM866'=> true,'866'=> true,'CP1251'=> true,'WINDOWS-1251'=> true,'WIN-1251'=> true,'1251'=> true,'CP1252'=> true,'WINDOWS-1252'=> true,'1252'=> true,'KOI8-R'=> true,'KOI8-RU'=> true,'KOI8R'=> true,'BIG5'=> true,'950'=> true,'GB2312'=> true,'936'=> true,'BIG5-HKSCS'=> true,'SHIFT_JIS'=> true,'SJIS'=> true,'932'=> true,'EUC-JP'=> true,'EUCJP'=> true,'ISO8859-5'=> true,'ISO-8859-5'=> true,'MACROMAN'=> true,
);
if (isset($htmlspecialcharsCharsets[$charset])) {
return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, $charset);
}
if (isset($htmlspecialcharsCharsets[strtoupper($charset)])) {
$htmlspecialcharsCharsets[$charset] = true;
return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, $charset);
}
$string = twig_convert_encoding($string,'UTF-8', $charset);
$string = htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
return twig_convert_encoding($string, $charset,'UTF-8');
case'js':
if ('UTF-8'!= $charset) {
$string = twig_convert_encoding($string,'UTF-8', $charset);
}
if (0 == strlen($string) ? false : (1 == preg_match('/^./su', $string) ? false : true)) {
throw new Twig_Error_Runtime('The string to escape is not a valid UTF-8 string.');
}
$string = preg_replace_callback('#[^a-zA-Z0-9,\._]#Su','_twig_escape_js_callback', $string);
if ('UTF-8'!= $charset) {
$string = twig_convert_encoding($string, $charset,'UTF-8');
}
return $string;
case'css':
if ('UTF-8'!= $charset) {
$string = twig_convert_encoding($string,'UTF-8', $charset);
}
if (0 == strlen($string) ? false : (1 == preg_match('/^./su', $string) ? false : true)) {
throw new Twig_Error_Runtime('The string to escape is not a valid UTF-8 string.');
}
$string = preg_replace_callback('#[^a-zA-Z0-9]#Su','_twig_escape_css_callback', $string);
if ('UTF-8'!= $charset) {
$string = twig_convert_encoding($string, $charset,'UTF-8');
}
return $string;
case'html_attr':
if ('UTF-8'!= $charset) {
$string = twig_convert_encoding($string,'UTF-8', $charset);
}
if (0 == strlen($string) ? false : (1 == preg_match('/^./su', $string) ? false : true)) {
throw new Twig_Error_Runtime('The string to escape is not a valid UTF-8 string.');
}
$string = preg_replace_callback('#[^a-zA-Z0-9,\.\-_]#Su','_twig_escape_html_attr_callback', $string);
if ('UTF-8'!= $charset) {
$string = twig_convert_encoding($string, $charset,'UTF-8');
}
return $string;
case'url':
if (PHP_VERSION <'5.3.0') {
return str_replace('%7E','~', rawurlencode($string));
}
return rawurlencode($string);
default:
throw new Twig_Error_Runtime(sprintf('Invalid escaping strategy "%s" (valid ones: html, js, url, css, and html_attr).', $strategy));
}
}
function twig_escape_filter_is_safe(Twig_Node $filterArgs)
{
foreach ($filterArgs as $arg) {
if ($arg instanceof Twig_Node_Expression_Constant) {
return array($arg->getAttribute('value'));
}
return array();
}
return array('html');
}
if (function_exists('mb_convert_encoding')) {
function twig_convert_encoding($string, $to, $from)
{
return mb_convert_encoding($string, $to, $from);
}
} elseif (function_exists('iconv')) {
function twig_convert_encoding($string, $to, $from)
{
return iconv($from, $to, $string);
}
} else {
function twig_convert_encoding($string, $to, $from)
{
throw new Twig_Error_Runtime('No suitable convert encoding function (use UTF-8 as your encoding or install the iconv or mbstring extension).');
}
}
function _twig_escape_js_callback($matches)
{
$char = $matches[0];
if (!isset($char[1])) {
return'\\x'.strtoupper(substr('00'.bin2hex($char), -2));
}
$char = twig_convert_encoding($char,'UTF-16BE','UTF-8');
return'\\u'.strtoupper(substr('0000'.bin2hex($char), -4));
}
function _twig_escape_css_callback($matches)
{
$char = $matches[0];
if (!isset($char[1])) {
$hex = ltrim(strtoupper(bin2hex($char)),'0');
if (0 === strlen($hex)) {
$hex ='0';
}
return'\\'.$hex.' ';
}
$char = twig_convert_encoding($char,'UTF-16BE','UTF-8');
return'\\'.ltrim(strtoupper(bin2hex($char)),'0').' ';
}
function _twig_escape_html_attr_callback($matches)
{
static $entityMap = array(
34 =>'quot',
38 =>'amp',
60 =>'lt',
62 =>'gt',
);
$chr = $matches[0];
$ord = ord($chr);
if (($ord <= 0x1f && $chr !="\t"&& $chr !="\n"&& $chr !="\r") || ($ord >= 0x7f && $ord <= 0x9f)) {
return'&#xFFFD;';
}
if (strlen($chr) == 1) {
$hex = strtoupper(substr('00'.bin2hex($chr), -2));
} else {
$chr = twig_convert_encoding($chr,'UTF-16BE','UTF-8');
$hex = strtoupper(substr('0000'.bin2hex($chr), -4));
}
$int = hexdec($hex);
if (array_key_exists($int, $entityMap)) {
return sprintf('&%s;', $entityMap[$int]);
}
return sprintf('&#x%s;', $hex);
}
if (function_exists('mb_get_info')) {
function twig_length_filter(Twig_Environment $env, $thing)
{
return is_scalar($thing) ? mb_strlen($thing, $env->getCharset()) : count($thing);
}
function twig_upper_filter(Twig_Environment $env, $string)
{
if (null !== ($charset = $env->getCharset())) {
return mb_strtoupper($string, $charset);
}
return strtoupper($string);
}
function twig_lower_filter(Twig_Environment $env, $string)
{
if (null !== ($charset = $env->getCharset())) {
return mb_strtolower($string, $charset);
}
return strtolower($string);
}
function twig_title_string_filter(Twig_Environment $env, $string)
{
if (null !== ($charset = $env->getCharset())) {
return mb_convert_case($string, MB_CASE_TITLE, $charset);
}
return ucwords(strtolower($string));
}
function twig_capitalize_string_filter(Twig_Environment $env, $string)
{
if (null !== ($charset = $env->getCharset())) {
return mb_strtoupper(mb_substr($string, 0, 1, $charset), $charset).
mb_strtolower(mb_substr($string, 1, mb_strlen($string, $charset), $charset), $charset);
}
return ucfirst(strtolower($string));
}
}
else {
function twig_length_filter(Twig_Environment $env, $thing)
{
return is_scalar($thing) ? strlen($thing) : count($thing);
}
function twig_title_string_filter(Twig_Environment $env, $string)
{
return ucwords(strtolower($string));
}
function twig_capitalize_string_filter(Twig_Environment $env, $string)
{
return ucfirst(strtolower($string));
}
}
function twig_ensure_traversable($seq)
{
if ($seq instanceof Traversable || is_array($seq)) {
return $seq;
}
return array();
}
function twig_test_empty($value)
{
if ($value instanceof Countable) {
return 0 == count($value);
}
return''=== $value || false === $value || null === $value || array() === $value;
}
function twig_test_iterable($value)
{
return $value instanceof Traversable || is_array($value);
}
function twig_include(Twig_Environment $env, $context, $template, $variables = array(), $withContext = true, $ignoreMissing = false, $sandboxed = false)
{
if ($withContext) {
$variables = array_merge($context, $variables);
}
if ($isSandboxed = $sandboxed && $env->hasExtension('sandbox')) {
$sandbox = $env->getExtension('sandbox');
if (!$alreadySandboxed = $sandbox->isSandboxed()) {
$sandbox->enableSandbox();
}
}
try {
return $env->resolveTemplate($template)->render($variables);
} catch (Twig_Error_Loader $e) {
if (!$ignoreMissing) {
throw $e;
}
}
if ($isSandboxed && !$alreadySandboxed) {
$sandbox->disableSandbox();
}
}
function twig_constant($constant, $object = null)
{
if (null !== $object) {
$constant = get_class($object).'::'.$constant;
}
return constant($constant);
}
function twig_array_batch($items, $size, $fill = null)
{
if ($items instanceof Traversable) {
$items = iterator_to_array($items, false);
}
$size = ceil($size);
$result = array_chunk($items, $size, true);
if (null !== $fill) {
$last = count($result) - 1;
$result[$last] = array_merge(
$result[$last],
array_fill(0, $size - count($result[$last]), $fill)
);
}
return $result;
}
}
namespace
{
class Twig_Extension_Debug extends Twig_Extension
{
public function getFunctions()
{
$isDumpOutputHtmlSafe = extension_loaded('xdebug')
&& (false === ini_get('xdebug.overload_var_dump') || ini_get('xdebug.overload_var_dump'))
&& (false === ini_get('html_errors') || ini_get('html_errors'))
||'cli'=== php_sapi_name()
;
return array(
new Twig_SimpleFunction('dump','twig_var_dump', array('is_safe'=> $isDumpOutputHtmlSafe ? array('html') : array(),'needs_context'=> true,'needs_environment'=> true)),
);
}
public function getName()
{
return'debug';
}
}
function twig_var_dump(Twig_Environment $env, $context)
{
if (!$env->isDebug()) {
return;
}
ob_start();
$count = func_num_args();
if (2 === $count) {
$vars = array();
foreach ($context as $key => $value) {
if (!$value instanceof Twig_Template) {
$vars[$key] = $value;
}
}
var_dump($vars);
} else {
for ($i = 2; $i < $count; $i++) {
var_dump(func_get_arg($i));
}
}
return ob_get_clean();
}
}
namespace
{
class Twig_Extension_Escaper extends Twig_Extension
{
protected $defaultStrategy;
public function __construct($defaultStrategy ='html')
{
$this->setDefaultStrategy($defaultStrategy);
}
public function getTokenParsers()
{
return array(new Twig_TokenParser_AutoEscape());
}
public function getNodeVisitors()
{
return array(new Twig_NodeVisitor_Escaper());
}
public function getFilters()
{
return array(
new Twig_SimpleFilter('raw','twig_raw_filter', array('is_safe'=> array('all'))),
);
}
public function setDefaultStrategy($defaultStrategy)
{
if (true === $defaultStrategy) {
$defaultStrategy ='html';
}
$this->defaultStrategy = $defaultStrategy;
}
public function getDefaultStrategy($filename)
{
if (!is_string($this->defaultStrategy) && is_callable($this->defaultStrategy)) {
return call_user_func($this->defaultStrategy, $filename);
}
return $this->defaultStrategy;
}
public function getName()
{
return'escaper';
}
}
function twig_raw_filter($string)
{
return $string;
}
}
namespace
{
class Twig_Extension_Optimizer extends Twig_Extension
{
protected $optimizers;
public function __construct($optimizers = -1)
{
$this->optimizers = $optimizers;
}
public function getNodeVisitors()
{
return array(new Twig_NodeVisitor_Optimizer($this->optimizers));
}
public function getName()
{
return'optimizer';
}
}
}
namespace
{
class Twig_Extension_Sandbox extends Twig_Extension
{
protected $sandboxedGlobally;
protected $sandboxed;
protected $policy;
public function __construct(Twig_Sandbox_SecurityPolicyInterface $policy, $sandboxed = false)
{
$this->policy = $policy;
$this->sandboxedGlobally = $sandboxed;
}
public function getTokenParsers()
{
return array(new Twig_TokenParser_Sandbox());
}
public function getNodeVisitors()
{
return array(new Twig_NodeVisitor_Sandbox());
}
public function enableSandbox()
{
$this->sandboxed = true;
}
public function disableSandbox()
{
$this->sandboxed = false;
}
public function isSandboxed()
{
return $this->sandboxedGlobally || $this->sandboxed;
}
public function isSandboxedGlobally()
{
return $this->sandboxedGlobally;
}
public function setSecurityPolicy(Twig_Sandbox_SecurityPolicyInterface $policy)
{
$this->policy = $policy;
}
public function getSecurityPolicy()
{
return $this->policy;
}
public function checkSecurity($tags, $filters, $functions)
{
if ($this->isSandboxed()) {
$this->policy->checkSecurity($tags, $filters, $functions);
}
}
public function checkMethodAllowed($obj, $method)
{
if ($this->isSandboxed()) {
$this->policy->checkMethodAllowed($obj, $method);
}
}
public function checkPropertyAllowed($obj, $method)
{
if ($this->isSandboxed()) {
$this->policy->checkPropertyAllowed($obj, $method);
}
}
public function ensureToStringAllowed($obj)
{
if (is_object($obj)) {
$this->policy->checkMethodAllowed($obj,'__toString');
}
return $obj;
}
public function getName()
{
return'sandbox';
}
}
}
namespace
{
class Twig_Extension_Staging extends Twig_Extension
{
protected $functions = array();
protected $filters = array();
protected $visitors = array();
protected $tokenParsers = array();
protected $globals = array();
protected $tests = array();
public function addFunction($name, $function)
{
$this->functions[$name] = $function;
}
public function getFunctions()
{
return $this->functions;
}
public function addFilter($name, $filter)
{
$this->filters[$name] = $filter;
}
public function getFilters()
{
return $this->filters;
}
public function addNodeVisitor(Twig_NodeVisitorInterface $visitor)
{
$this->visitors[] = $visitor;
}
public function getNodeVisitors()
{
return $this->visitors;
}
public function addTokenParser(Twig_TokenParserInterface $parser)
{
$this->tokenParsers[] = $parser;
}
public function getTokenParsers()
{
return $this->tokenParsers;
}
public function addGlobal($name, $value)
{
$this->globals[$name] = $value;
}
public function getGlobals()
{
return $this->globals;
}
public function addTest($name, $test)
{
$this->tests[$name] = $test;
}
public function getTests()
{
return $this->tests;
}
public function getName()
{
return'staging';
}
}
}
namespace
{
interface Twig_FilterCallableInterface
{
public function getCallable();
}
}
namespace
{
interface Twig_FilterInterface
{
public function compile();
public function needsEnvironment();
public function needsContext();
public function getSafe(Twig_Node $filterArgs);
public function getPreservesSafety();
public function getPreEscape();
public function setArguments($arguments);
public function getArguments();
}
}
namespace
{
abstract class Twig_Filter implements Twig_FilterInterface, Twig_FilterCallableInterface
{
protected $options;
protected $arguments = array();
public function __construct(array $options = array())
{
$this->options = array_merge(array('needs_environment'=> false,'needs_context'=> false,'pre_escape'=> null,'preserves_safety'=> null,'callable'=> null,
), $options);
}
public function setArguments($arguments)
{
$this->arguments = $arguments;
}
public function getArguments()
{
return $this->arguments;
}
public function needsEnvironment()
{
return $this->options['needs_environment'];
}
public function needsContext()
{
return $this->options['needs_context'];
}
public function getSafe(Twig_Node $filterArgs)
{
if (isset($this->options['is_safe'])) {
return $this->options['is_safe'];
}
if (isset($this->options['is_safe_callback'])) {
return call_user_func($this->options['is_safe_callback'], $filterArgs);
}
}
public function getPreservesSafety()
{
return $this->options['preserves_safety'];
}
public function getPreEscape()
{
return $this->options['pre_escape'];
}
public function getCallable()
{
return $this->options['callable'];
}
}
}
namespace
{
class Twig_Filter_Function extends Twig_Filter
{
protected $function;
public function __construct($function, array $options = array())
{
$options['callable'] = $function;
parent::__construct($options);
$this->function = $function;
}
public function compile()
{
return $this->function;
}
}
}
namespace
{
class Twig_Filter_Method extends Twig_Filter
{
protected $extension;
protected $method;
public function __construct(Twig_ExtensionInterface $extension, $method, array $options = array())
{
$options['callable'] = array($extension, $method);
parent::__construct($options);
$this->extension = $extension;
$this->method = $method;
}
public function compile()
{
return sprintf('$this->env->getExtension(\'%s\')->%s', $this->extension->getName(), $this->method);
}
}
}
namespace
{
class Twig_Filter_Node extends Twig_Filter
{
protected $class;
public function __construct($class, array $options = array())
{
parent::__construct($options);
$this->class = $class;
}
public function getClass()
{
return $this->class;
}
public function compile()
{
}
}
}
namespace
{
interface Twig_FunctionCallableInterface
{
public function getCallable();
}
}
namespace
{
interface Twig_FunctionInterface
{
public function compile();
public function needsEnvironment();
public function needsContext();
public function getSafe(Twig_Node $filterArgs);
public function setArguments($arguments);
public function getArguments();
}
}
namespace
{
abstract class Twig_Function implements Twig_FunctionInterface, Twig_FunctionCallableInterface
{
protected $options;
protected $arguments = array();
public function __construct(array $options = array())
{
$this->options = array_merge(array('needs_environment'=> false,'needs_context'=> false,'callable'=> null,
), $options);
}
public function setArguments($arguments)
{
$this->arguments = $arguments;
}
public function getArguments()
{
return $this->arguments;
}
public function needsEnvironment()
{
return $this->options['needs_environment'];
}
public function needsContext()
{
return $this->options['needs_context'];
}
public function getSafe(Twig_Node $functionArgs)
{
if (isset($this->options['is_safe'])) {
return $this->options['is_safe'];
}
if (isset($this->options['is_safe_callback'])) {
return call_user_func($this->options['is_safe_callback'], $functionArgs);
}
return array();
}
public function getCallable()
{
return $this->options['callable'];
}
}
}
namespace
{
class Twig_Function_Function extends Twig_Function
{
protected $function;
public function __construct($function, array $options = array())
{
$options['callable'] = $function;
parent::__construct($options);
$this->function = $function;
}
public function compile()
{
return $this->function;
}
}
}
namespace
{
class Twig_Function_Method extends Twig_Function
{
protected $extension;
protected $method;
public function __construct(Twig_ExtensionInterface $extension, $method, array $options = array())
{
$options['callable'] = array($extension, $method);
parent::__construct($options);
$this->extension = $extension;
$this->method = $method;
}
public function compile()
{
return sprintf('$this->env->getExtension(\'%s\')->%s', $this->extension->getName(), $this->method);
}
}
}
namespace
{
class Twig_Function_Node extends Twig_Function
{
protected $class;
public function __construct($class, array $options = array())
{
parent::__construct($options);
$this->class = $class;
}
public function getClass()
{
return $this->class;
}
public function compile()
{
}
}
}
namespace
{
interface Twig_LexerInterface
{
public function tokenize($code, $filename = null);
}
}
namespace
{
class Twig_Lexer implements Twig_LexerInterface
{
protected $tokens;
protected $code;
protected $cursor;
protected $lineno;
protected $end;
protected $state;
protected $states;
protected $brackets;
protected $env;
protected $filename;
protected $options;
protected $regexes;
protected $position;
protected $positions;
protected $currentVarBlockLine;
const STATE_DATA = 0;
const STATE_BLOCK = 1;
const STATE_VAR = 2;
const STATE_STRING = 3;
const STATE_INTERPOLATION = 4;
const REGEX_NAME ='/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/A';
const REGEX_NUMBER ='/[0-9]+(?:\.[0-9]+)?/A';
const REGEX_STRING ='/"([^#"\\\\]*(?:\\\\.[^#"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\'/As';
const REGEX_DQ_STRING_DELIM ='/"/A';
const REGEX_DQ_STRING_PART ='/[^#"\\\\]*(?:(?:\\\\.|#(?!\{))[^#"\\\\]*)*/As';
const PUNCTUATION ='()[]{}?:.,|';
public function __construct(Twig_Environment $env, array $options = array())
{
$this->env = $env;
$this->options = array_merge(array('tag_comment'=> array('{#','#}'),'tag_block'=> array('{%','%}'),'tag_variable'=> array('{{','}}'),'whitespace_trim'=>'-','interpolation'=> array('#{','}'),
), $options);
$this->regexes = array('lex_var'=>'/\s*'.preg_quote($this->options['whitespace_trim'].$this->options['tag_variable'][1],'/').'\s*|\s*'.preg_quote($this->options['tag_variable'][1],'/').'/A','lex_block'=>'/\s*(?:'.preg_quote($this->options['whitespace_trim'].$this->options['tag_block'][1],'/').'\s*|\s*'.preg_quote($this->options['tag_block'][1],'/').')\n?/A','lex_raw_data'=>'/('.preg_quote($this->options['tag_block'][0].$this->options['whitespace_trim'],'/').'|'.preg_quote($this->options['tag_block'][0],'/').')\s*(?:end%s)\s*(?:'.preg_quote($this->options['whitespace_trim'].$this->options['tag_block'][1],'/').'\s*|\s*'.preg_quote($this->options['tag_block'][1],'/').')/s','operator'=> $this->getOperatorRegex(),'lex_comment'=>'/(?:'.preg_quote($this->options['whitespace_trim'],'/').preg_quote($this->options['tag_comment'][1],'/').'\s*|'.preg_quote($this->options['tag_comment'][1],'/').')\n?/s','lex_block_raw'=>'/\s*(raw|verbatim)\s*(?:'.preg_quote($this->options['whitespace_trim'].$this->options['tag_block'][1],'/').'\s*|\s*'.preg_quote($this->options['tag_block'][1],'/').')/As','lex_block_line'=>'/\s*line\s+(\d+)\s*'.preg_quote($this->options['tag_block'][1],'/').'/As','lex_tokens_start'=>'/('.preg_quote($this->options['tag_variable'][0],'/').'|'.preg_quote($this->options['tag_block'][0],'/').'|'.preg_quote($this->options['tag_comment'][0],'/').')('.preg_quote($this->options['whitespace_trim'],'/').')?/s','interpolation_start'=>'/'.preg_quote($this->options['interpolation'][0],'/').'\s*/A','interpolation_end'=>'/\s*'.preg_quote($this->options['interpolation'][1],'/').'/A',
);
}
public function tokenize($code, $filename = null)
{
if (function_exists('mb_internal_encoding') && ((int) ini_get('mbstring.func_overload')) & 2) {
$mbEncoding = mb_internal_encoding();
mb_internal_encoding('ASCII');
}
$this->code = str_replace(array("\r\n","\r"),"\n", $code);
$this->filename = $filename;
$this->cursor = 0;
$this->lineno = 1;
$this->end = strlen($this->code);
$this->tokens = array();
$this->state = self::STATE_DATA;
$this->states = array();
$this->brackets = array();
$this->position = -1;
preg_match_all($this->regexes['lex_tokens_start'], $this->code, $matches, PREG_OFFSET_CAPTURE);
$this->positions = $matches;
while ($this->cursor < $this->end) {
switch ($this->state) {
case self::STATE_DATA:
$this->lexData();
break;
case self::STATE_BLOCK:
$this->lexBlock();
break;
case self::STATE_VAR:
$this->lexVar();
break;
case self::STATE_STRING:
$this->lexString();
break;
case self::STATE_INTERPOLATION:
$this->lexInterpolation();
break;
}
}
$this->pushToken(Twig_Token::EOF_TYPE);
if (!empty($this->brackets)) {
list($expect, $lineno) = array_pop($this->brackets);
throw new Twig_Error_Syntax(sprintf('Unclosed "%s"', $expect), $lineno, $this->filename);
}
if (isset($mbEncoding)) {
mb_internal_encoding($mbEncoding);
}
return new Twig_TokenStream($this->tokens, $this->filename);
}
protected function lexData()
{
if ($this->position == count($this->positions[0]) - 1) {
$this->pushToken(Twig_Token::TEXT_TYPE, substr($this->code, $this->cursor));
$this->cursor = $this->end;
return;
}
$position = $this->positions[0][++$this->position];
while ($position[1] < $this->cursor) {
if ($this->position == count($this->positions[0]) - 1) {
return;
}
$position = $this->positions[0][++$this->position];
}
$text = $textContent = substr($this->code, $this->cursor, $position[1] - $this->cursor);
if (isset($this->positions[2][$this->position][0])) {
$text = rtrim($text);
}
$this->pushToken(Twig_Token::TEXT_TYPE, $text);
$this->moveCursor($textContent.$position[0]);
switch ($this->positions[1][$this->position][0]) {
case $this->options['tag_comment'][0]:
$this->lexComment();
break;
case $this->options['tag_block'][0]:
if (preg_match($this->regexes['lex_block_raw'], $this->code, $match, null, $this->cursor)) {
$this->moveCursor($match[0]);
$this->lexRawData($match[1]);
} elseif (preg_match($this->regexes['lex_block_line'], $this->code, $match, null, $this->cursor)) {
$this->moveCursor($match[0]);
$this->lineno = (int) $match[1];
} else {
$this->pushToken(Twig_Token::BLOCK_START_TYPE);
$this->pushState(self::STATE_BLOCK);
$this->currentVarBlockLine = $this->lineno;
}
break;
case $this->options['tag_variable'][0]:
$this->pushToken(Twig_Token::VAR_START_TYPE);
$this->pushState(self::STATE_VAR);
$this->currentVarBlockLine = $this->lineno;
break;
}
}
protected function lexBlock()
{
if (empty($this->brackets) && preg_match($this->regexes['lex_block'], $this->code, $match, null, $this->cursor)) {
$this->pushToken(Twig_Token::BLOCK_END_TYPE);
$this->moveCursor($match[0]);
$this->popState();
} else {
$this->lexExpression();
}
}
protected function lexVar()
{
if (empty($this->brackets) && preg_match($this->regexes['lex_var'], $this->code, $match, null, $this->cursor)) {
$this->pushToken(Twig_Token::VAR_END_TYPE);
$this->moveCursor($match[0]);
$this->popState();
} else {
$this->lexExpression();
}
}
protected function lexExpression()
{
if (preg_match('/\s+/A', $this->code, $match, null, $this->cursor)) {
$this->moveCursor($match[0]);
if ($this->cursor >= $this->end) {
throw new Twig_Error_Syntax(sprintf('Unclosed "%s"', $this->state === self::STATE_BLOCK ?'block':'variable'), $this->currentVarBlockLine, $this->filename);
}
}
if (preg_match($this->regexes['operator'], $this->code, $match, null, $this->cursor)) {
$this->pushToken(Twig_Token::OPERATOR_TYPE, $match[0]);
$this->moveCursor($match[0]);
}
elseif (preg_match(self::REGEX_NAME, $this->code, $match, null, $this->cursor)) {
$this->pushToken(Twig_Token::NAME_TYPE, $match[0]);
$this->moveCursor($match[0]);
}
elseif (preg_match(self::REGEX_NUMBER, $this->code, $match, null, $this->cursor)) {
$number = (float) $match[0]; if (ctype_digit($match[0]) && $number <= PHP_INT_MAX) {
$number = (int) $match[0]; }
$this->pushToken(Twig_Token::NUMBER_TYPE, $number);
$this->moveCursor($match[0]);
}
elseif (false !== strpos(self::PUNCTUATION, $this->code[$this->cursor])) {
if (false !== strpos('([{', $this->code[$this->cursor])) {
$this->brackets[] = array($this->code[$this->cursor], $this->lineno);
}
elseif (false !== strpos(')]}', $this->code[$this->cursor])) {
if (empty($this->brackets)) {
throw new Twig_Error_Syntax(sprintf('Unexpected "%s"', $this->code[$this->cursor]), $this->lineno, $this->filename);
}
list($expect, $lineno) = array_pop($this->brackets);
if ($this->code[$this->cursor] != strtr($expect,'([{',')]}')) {
throw new Twig_Error_Syntax(sprintf('Unclosed "%s"', $expect), $lineno, $this->filename);
}
}
$this->pushToken(Twig_Token::PUNCTUATION_TYPE, $this->code[$this->cursor]);
++$this->cursor;
}
elseif (preg_match(self::REGEX_STRING, $this->code, $match, null, $this->cursor)) {
$this->pushToken(Twig_Token::STRING_TYPE, stripcslashes(substr($match[0], 1, -1)));
$this->moveCursor($match[0]);
}
elseif (preg_match(self::REGEX_DQ_STRING_DELIM, $this->code, $match, null, $this->cursor)) {
$this->brackets[] = array('"', $this->lineno);
$this->pushState(self::STATE_STRING);
$this->moveCursor($match[0]);
}
else {
throw new Twig_Error_Syntax(sprintf('Unexpected character "%s"', $this->code[$this->cursor]), $this->lineno, $this->filename);
}
}
protected function lexRawData($tag)
{
if (!preg_match(str_replace('%s', $tag, $this->regexes['lex_raw_data']), $this->code, $match, PREG_OFFSET_CAPTURE, $this->cursor)) {
throw new Twig_Error_Syntax(sprintf('Unexpected end of file: Unclosed "%s" block', $tag), $this->lineno, $this->filename);
}
$text = substr($this->code, $this->cursor, $match[0][1] - $this->cursor);
$this->moveCursor($text.$match[0][0]);
if (false !== strpos($match[1][0], $this->options['whitespace_trim'])) {
$text = rtrim($text);
}
$this->pushToken(Twig_Token::TEXT_TYPE, $text);
}
protected function lexComment()
{
if (!preg_match($this->regexes['lex_comment'], $this->code, $match, PREG_OFFSET_CAPTURE, $this->cursor)) {
throw new Twig_Error_Syntax('Unclosed comment', $this->lineno, $this->filename);
}
$this->moveCursor(substr($this->code, $this->cursor, $match[0][1] - $this->cursor).$match[0][0]);
}
protected function lexString()
{
if (preg_match($this->regexes['interpolation_start'], $this->code, $match, null, $this->cursor)) {
$this->brackets[] = array($this->options['interpolation'][0], $this->lineno);
$this->pushToken(Twig_Token::INTERPOLATION_START_TYPE);
$this->moveCursor($match[0]);
$this->pushState(self::STATE_INTERPOLATION);
} elseif (preg_match(self::REGEX_DQ_STRING_PART, $this->code, $match, null, $this->cursor) && strlen($match[0]) > 0) {
$this->pushToken(Twig_Token::STRING_TYPE, stripcslashes($match[0]));
$this->moveCursor($match[0]);
} elseif (preg_match(self::REGEX_DQ_STRING_DELIM, $this->code, $match, null, $this->cursor)) {
list($expect, $lineno) = array_pop($this->brackets);
if ($this->code[$this->cursor] !='"') {
throw new Twig_Error_Syntax(sprintf('Unclosed "%s"', $expect), $lineno, $this->filename);
}
$this->popState();
++$this->cursor;
}
}
protected function lexInterpolation()
{
$bracket = end($this->brackets);
if ($this->options['interpolation'][0] === $bracket[0] && preg_match($this->regexes['interpolation_end'], $this->code, $match, null, $this->cursor)) {
array_pop($this->brackets);
$this->pushToken(Twig_Token::INTERPOLATION_END_TYPE);
$this->moveCursor($match[0]);
$this->popState();
} else {
$this->lexExpression();
}
}
protected function pushToken($type, $value ='')
{
if (Twig_Token::TEXT_TYPE === $type &&''=== $value) {
return;
}
$this->tokens[] = new Twig_Token($type, $value, $this->lineno);
}
protected function moveCursor($text)
{
$this->cursor += strlen($text);
$this->lineno += substr_count($text,"\n");
}
protected function getOperatorRegex()
{
$operators = array_merge(
array('='),
array_keys($this->env->getUnaryOperators()),
array_keys($this->env->getBinaryOperators())
);
$operators = array_combine($operators, array_map('strlen', $operators));
arsort($operators);
$regex = array();
foreach ($operators as $operator => $length) {
if (ctype_alpha($operator[$length - 1])) {
$regex[] = preg_quote($operator,'/').'(?=[\s()])';
} else {
$regex[] = preg_quote($operator,'/');
}
}
return'/'.implode('|', $regex).'/A';
}
protected function pushState($state)
{
$this->states[] = $this->state;
$this->state = $state;
}
protected function popState()
{
if (0 === count($this->states)) {
throw new Exception('Cannot pop state without a previous state');
}
$this->state = array_pop($this->states);
}
}
}
namespace
{
interface Twig_LoaderInterface
{
public function getSource($name);
public function getCacheKey($name);
public function isFresh($name, $time);
}
}
namespace
{
interface Twig_ExistsLoaderInterface
{
public function exists($name);
}
}
namespace
{
class Twig_Loader_Array implements Twig_LoaderInterface, Twig_ExistsLoaderInterface
{
protected $templates;
public function __construct(array $templates)
{
$this->templates = array();
foreach ($templates as $name => $template) {
$this->templates[$name] = $template;
}
}
public function setTemplate($name, $template)
{
$this->templates[(string) $name] = $template;
}
public function getSource($name)
{
$name = (string) $name;
if (!isset($this->templates[$name])) {
throw new Twig_Error_Loader(sprintf('Template "%s" is not defined.', $name));
}
return $this->templates[$name];
}
public function exists($name)
{
return isset($this->templates[(string) $name]);
}
public function getCacheKey($name)
{
$name = (string) $name;
if (!isset($this->templates[$name])) {
throw new Twig_Error_Loader(sprintf('Template "%s" is not defined.', $name));
}
return $this->templates[$name];
}
public function isFresh($name, $time)
{
$name = (string) $name;
if (!isset($this->templates[$name])) {
throw new Twig_Error_Loader(sprintf('Template "%s" is not defined.', $name));
}
return true;
}
}
}
namespace
{
class Twig_Loader_Chain implements Twig_LoaderInterface, Twig_ExistsLoaderInterface
{
private $hasSourceCache = array();
protected $loaders;
public function __construct(array $loaders = array())
{
$this->loaders = array();
foreach ($loaders as $loader) {
$this->addLoader($loader);
}
}
public function addLoader(Twig_LoaderInterface $loader)
{
$this->loaders[] = $loader;
$this->hasSourceCache = array();
}
public function getSource($name)
{
$exceptions = array();
foreach ($this->loaders as $loader) {
if ($loader instanceof Twig_ExistsLoaderInterface && !$loader->exists($name)) {
continue;
}
try {
return $loader->getSource($name);
} catch (Twig_Error_Loader $e) {
$exceptions[] = $e->getMessage();
}
}
throw new Twig_Error_Loader(sprintf('Template "%s" is not defined (%s).', $name, implode(', ', $exceptions)));
}
public function exists($name)
{
$name = (string) $name;
if (isset($this->hasSourceCache[$name])) {
return $this->hasSourceCache[$name];
}
foreach ($this->loaders as $loader) {
if ($loader instanceof Twig_ExistsLoaderInterface) {
if ($loader->exists($name)) {
return $this->hasSourceCache[$name] = true;
}
continue;
}
try {
$loader->getSource($name);
return $this->hasSourceCache[$name] = true;
} catch (Twig_Error_Loader $e) {
}
}
return $this->hasSourceCache[$name] = false;
}
public function getCacheKey($name)
{
$exceptions = array();
foreach ($this->loaders as $loader) {
if ($loader instanceof Twig_ExistsLoaderInterface && !$loader->exists($name)) {
continue;
}
try {
return $loader->getCacheKey($name);
} catch (Twig_Error_Loader $e) {
$exceptions[] = get_class($loader).': '.$e->getMessage();
}
}
throw new Twig_Error_Loader(sprintf('Template "%s" is not defined (%s).', $name, implode(' ', $exceptions)));
}
public function isFresh($name, $time)
{
$exceptions = array();
foreach ($this->loaders as $loader) {
if ($loader instanceof Twig_ExistsLoaderInterface && !$loader->exists($name)) {
continue;
}
try {
return $loader->isFresh($name, $time);
} catch (Twig_Error_Loader $e) {
$exceptions[] = get_class($loader).': '.$e->getMessage();
}
}
throw new Twig_Error_Loader(sprintf('Template "%s" is not defined (%s).', $name, implode(' ', $exceptions)));
}
}
}
namespace
{
class Twig_Loader_Filesystem implements Twig_LoaderInterface, Twig_ExistsLoaderInterface
{
protected $paths;
protected $cache;
public function __construct($paths = array())
{
if ($paths) {
$this->setPaths($paths);
}
}
public function getPaths($namespace ='__main__')
{
return isset($this->paths[$namespace]) ? $this->paths[$namespace] : array();
}
public function getNamespaces()
{
return array_keys($this->paths);
}
public function setPaths($paths, $namespace ='__main__')
{
if (!is_array($paths)) {
$paths = array($paths);
}
$this->paths[$namespace] = array();
foreach ($paths as $path) {
$this->addPath($path, $namespace);
}
}
public function addPath($path, $namespace ='__main__')
{
$this->cache = array();
if (!is_dir($path)) {
throw new Twig_Error_Loader(sprintf('The "%s" directory does not exist.', $path));
}
$this->paths[$namespace][] = rtrim($path,'/\\');
}
public function prependPath($path, $namespace ='__main__')
{
$this->cache = array();
if (!is_dir($path)) {
throw new Twig_Error_Loader(sprintf('The "%s" directory does not exist.', $path));
}
$path = rtrim($path,'/\\');
if (!isset($this->paths[$namespace])) {
$this->paths[$namespace][] = $path;
} else {
array_unshift($this->paths[$namespace], $path);
}
}
public function getSource($name)
{
return file_get_contents($this->findTemplate($name));
}
public function getCacheKey($name)
{
return $this->findTemplate($name);
}
public function exists($name)
{
$name = (string) $name;
if (isset($this->cache[$name])) {
return true;
}
try {
$this->findTemplate($name);
return true;
} catch (Twig_Error_Loader $exception) {
return false;
}
}
public function isFresh($name, $time)
{
return filemtime($this->findTemplate($name)) <= $time;
}
protected function findTemplate($name)
{
$name = (string) $name;
$name = preg_replace('#/{2,}#','/', strtr($name,'\\','/'));
if (isset($this->cache[$name])) {
return $this->cache[$name];
}
$this->validateName($name);
$namespace ='__main__';
if (isset($name[0]) &&'@'== $name[0]) {
if (false === $pos = strpos($name,'/')) {
throw new Twig_Error_Loader(sprintf('Malformed namespaced template name "%s" (expecting "@namespace/template_name").', $name));
}
$namespace = substr($name, 1, $pos - 1);
$name = substr($name, $pos + 1);
}
if (!isset($this->paths[$namespace])) {
throw new Twig_Error_Loader(sprintf('There are no registered paths for namespace "%s".', $namespace));
}
foreach ($this->paths[$namespace] as $path) {
if (is_file($path.'/'.$name)) {
return $this->cache[$name] = $path.'/'.$name;
}
}
throw new Twig_Error_Loader(sprintf('Unable to find template "%s" (looked into: %s).', $name, implode(', ', $this->paths[$namespace])));
}
protected function validateName($name)
{
if (false !== strpos($name,"\0")) {
throw new Twig_Error_Loader('A template name cannot contain NUL bytes.');
}
$name = ltrim($name,'/');
$parts = explode('/', $name);
$level = 0;
foreach ($parts as $part) {
if ('..'=== $part) {
--$level;
} elseif ('.'!== $part) {
++$level;
}
if ($level < 0) {
throw new Twig_Error_Loader(sprintf('Looks like you try to load a template outside configured directories (%s).', $name));
}
}
}
}
}
namespace
{
class Twig_Loader_String implements Twig_LoaderInterface, Twig_ExistsLoaderInterface
{
public function getSource($name)
{
return $name;
}
public function exists($name)
{
return true;
}
public function getCacheKey($name)
{
return $name;
}
public function isFresh($name, $time)
{
return true;
}
}
}
namespace
{
class Twig_Markup implements Countable
{
protected $content;
protected $charset;
public function __construct($content, $charset)
{
$this->content = (string) $content;
$this->charset = $charset;
}
public function __toString()
{
return $this->content;
}
public function count()
{
return function_exists('mb_get_info') ? mb_strlen($this->content, $this->charset) : strlen($this->content);
}
}
}
namespace
{
interface Twig_NodeInterface extends Countable, IteratorAggregate
{
public function compile(Twig_Compiler $compiler);
public function getLine();
public function getNodeTag();
}
}
namespace
{
class Twig_Node implements Twig_NodeInterface
{
protected $nodes;
protected $attributes;
protected $lineno;
protected $tag;
public function __construct(array $nodes = array(), array $attributes = array(), $lineno = 0, $tag = null)
{
$this->nodes = $nodes;
$this->attributes = $attributes;
$this->lineno = $lineno;
$this->tag = $tag;
}
public function __toString()
{
$attributes = array();
foreach ($this->attributes as $name => $value) {
$attributes[] = sprintf('%s: %s', $name, str_replace("\n",'', var_export($value, true)));
}
$repr = array(get_class($this).'('.implode(', ', $attributes));
if (count($this->nodes)) {
foreach ($this->nodes as $name => $node) {
$len = strlen($name) + 4;
$noderepr = array();
foreach (explode("\n", (string) $node) as $line) {
$noderepr[] = str_repeat(' ', $len).$line;
}
$repr[] = sprintf('  %s: %s', $name, ltrim(implode("\n", $noderepr)));
}
$repr[] =')';
} else {
$repr[0] .=')';
}
return implode("\n", $repr);
}
public function toXml($asDom = false)
{
$dom = new DOMDocument('1.0','UTF-8');
$dom->formatOutput = true;
$dom->appendChild($xml = $dom->createElement('twig'));
$xml->appendChild($node = $dom->createElement('node'));
$node->setAttribute('class', get_class($this));
foreach ($this->attributes as $name => $value) {
$node->appendChild($attribute = $dom->createElement('attribute'));
$attribute->setAttribute('name', $name);
$attribute->appendChild($dom->createTextNode($value));
}
foreach ($this->nodes as $name => $n) {
if (null === $n) {
continue;
}
$child = $n->toXml(true)->getElementsByTagName('node')->item(0);
$child = $dom->importNode($child, true);
$child->setAttribute('name', $name);
$node->appendChild($child);
}
return $asDom ? $dom : $dom->saveXml();
}
public function compile(Twig_Compiler $compiler)
{
foreach ($this->nodes as $node) {
$node->compile($compiler);
}
}
public function getLine()
{
return $this->lineno;
}
public function getNodeTag()
{
return $this->tag;
}
public function hasAttribute($name)
{
return array_key_exists($name, $this->attributes);
}
public function getAttribute($name)
{
if (!array_key_exists($name, $this->attributes)) {
throw new LogicException(sprintf('Attribute "%s" does not exist for Node "%s".', $name, get_class($this)));
}
return $this->attributes[$name];
}
public function setAttribute($name, $value)
{
$this->attributes[$name] = $value;
}
public function removeAttribute($name)
{
unset($this->attributes[$name]);
}
public function hasNode($name)
{
return array_key_exists($name, $this->nodes);
}
public function getNode($name)
{
if (!array_key_exists($name, $this->nodes)) {
throw new LogicException(sprintf('Node "%s" does not exist for Node "%s".', $name, get_class($this)));
}
return $this->nodes[$name];
}
public function setNode($name, $node = null)
{
$this->nodes[$name] = $node;
}
public function removeNode($name)
{
unset($this->nodes[$name]);
}
public function count()
{
return count($this->nodes);
}
public function getIterator()
{
return new ArrayIterator($this->nodes);
}
}
}
namespace
{
class Twig_Node_AutoEscape extends Twig_Node
{
public function __construct($value, Twig_NodeInterface $body, $lineno, $tag ='autoescape')
{
parent::__construct(array('body'=> $body), array('value'=> $value), $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
$compiler->subcompile($this->getNode('body'));
}
}
}
namespace
{
class Twig_Node_Block extends Twig_Node
{
public function __construct($name, Twig_NodeInterface $body, $lineno, $tag = null)
{
parent::__construct(array('body'=> $body), array('name'=> $name), $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
$compiler
->addDebugInfo($this)
->write(sprintf("public function block_%s(\$context, array \$blocks = array())\n", $this->getAttribute('name')),"{\n")
->indent()
;
$compiler
->subcompile($this->getNode('body'))
->outdent()
->write("}\n\n")
;
}
}
}
namespace
{
interface Twig_NodeOutputInterface
{
}
}
namespace
{
class Twig_Node_BlockReference extends Twig_Node implements Twig_NodeOutputInterface
{
public function __construct($name, $lineno, $tag = null)
{
parent::__construct(array(), array('name'=> $name), $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
$compiler
->addDebugInfo($this)
->write(sprintf("\$this->displayBlock('%s', \$context, \$blocks);\n", $this->getAttribute('name')))
;
}
}
}
namespace
{
class Twig_Node_Body extends Twig_Node
{
}
}
namespace
{
class Twig_Node_Do extends Twig_Node
{
public function __construct(Twig_Node_Expression $expr, $lineno, $tag = null)
{
parent::__construct(array('expr'=> $expr), array(), $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
$compiler
->addDebugInfo($this)
->write('')
->subcompile($this->getNode('expr'))
->raw(";\n")
;
}
}
}
namespace
{
class Twig_Node_Include extends Twig_Node implements Twig_NodeOutputInterface
{
public function __construct(Twig_Node_Expression $expr, Twig_Node_Expression $variables = null, $only = false, $ignoreMissing = false, $lineno, $tag = null)
{
parent::__construct(array('expr'=> $expr,'variables'=> $variables), array('only'=> (Boolean) $only,'ignore_missing'=> (Boolean) $ignoreMissing), $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
$compiler->addDebugInfo($this);
if ($this->getAttribute('ignore_missing')) {
$compiler
->write("try {\n")
->indent()
;
}
$this->addGetTemplate($compiler);
$compiler->raw('->display(');
$this->addTemplateArguments($compiler);
$compiler->raw(");\n");
if ($this->getAttribute('ignore_missing')) {
$compiler
->outdent()
->write("} catch (Twig_Error_Loader \$e) {\n")
->indent()
->write("// ignore missing template\n")
->outdent()
->write("}\n\n")
;
}
}
protected function addGetTemplate(Twig_Compiler $compiler)
{
if ($this->getNode('expr') instanceof Twig_Node_Expression_Constant) {
$compiler
->write("\$this->env->loadTemplate(")
->subcompile($this->getNode('expr'))
->raw(")")
;
} else {
$compiler
->write("\$template = \$this->env->resolveTemplate(")
->subcompile($this->getNode('expr'))
->raw(");\n")
->write('$template')
;
}
}
protected function addTemplateArguments(Twig_Compiler $compiler)
{
if (false === $this->getAttribute('only')) {
if (null === $this->getNode('variables')) {
$compiler->raw('$context');
} else {
$compiler
->raw('array_merge($context, ')
->subcompile($this->getNode('variables'))
->raw(')')
;
}
} else {
if (null === $this->getNode('variables')) {
$compiler->raw('array()');
} else {
$compiler->subcompile($this->getNode('variables'));
}
}
}
}
}
namespace
{
class Twig_Node_Embed extends Twig_Node_Include
{
public function __construct($filename, $index, Twig_Node_Expression $variables = null, $only = false, $ignoreMissing = false, $lineno, $tag = null)
{
parent::__construct(new Twig_Node_Expression_Constant('not_used', $lineno), $variables, $only, $ignoreMissing, $lineno, $tag);
$this->setAttribute('filename', $filename);
$this->setAttribute('index', $index);
}
protected function addGetTemplate(Twig_Compiler $compiler)
{
$compiler
->write("\$this->env->loadTemplate(")
->string($this->getAttribute('filename'))
->raw(', ')
->string($this->getAttribute('index'))
->raw(")")
;
}
}
}
namespace
{
abstract class Twig_Node_Expression extends Twig_Node
{
}
}
namespace
{
class Twig_Node_Expression_Array extends Twig_Node_Expression
{
protected $index;
public function __construct(array $elements, $lineno)
{
parent::__construct($elements, array(), $lineno);
$this->index = -1;
foreach ($this->getKeyValuePairs() as $pair) {
if ($pair['key'] instanceof Twig_Node_Expression_Constant && ctype_digit((string) $pair['key']->getAttribute('value')) && $pair['key']->getAttribute('value') > $this->index) {
$this->index = $pair['key']->getAttribute('value');
}
}
}
public function getKeyValuePairs()
{
$pairs = array();
foreach (array_chunk($this->nodes, 2) as $pair) {
$pairs[] = array('key'=> $pair[0],'value'=> $pair[1],
);
}
return $pairs;
}
public function hasElement(Twig_Node_Expression $key)
{
foreach ($this->getKeyValuePairs() as $pair) {
if ((string) $key == (string) $pair['key']) {
return true;
}
}
return false;
}
public function addElement(Twig_Node_Expression $value, Twig_Node_Expression $key = null)
{
if (null === $key) {
$key = new Twig_Node_Expression_Constant(++$this->index, $value->getLine());
}
array_push($this->nodes, $key, $value);
}
public function compile(Twig_Compiler $compiler)
{
$compiler->raw('array(');
$first = true;
foreach ($this->getKeyValuePairs() as $pair) {
if (!$first) {
$compiler->raw(', ');
}
$first = false;
$compiler
->subcompile($pair['key'])
->raw(' => ')
->subcompile($pair['value'])
;
}
$compiler->raw(')');
}
}
}
namespace
{
class Twig_Node_Expression_Name extends Twig_Node_Expression
{
protected $specialVars = array('_self'=>'$this','_context'=>'$context','_charset'=>'$this->env->getCharset()',
);
public function __construct($name, $lineno)
{
parent::__construct(array(), array('name'=> $name,'is_defined_test'=> false,'ignore_strict_check'=> false,'always_defined'=> false), $lineno);
}
public function compile(Twig_Compiler $compiler)
{
$name = $this->getAttribute('name');
if ($this->getAttribute('is_defined_test')) {
if ($this->isSpecial()) {
$compiler->repr(true);
} else {
$compiler->raw('array_key_exists(')->repr($name)->raw(', $context)');
}
} elseif ($this->isSpecial()) {
$compiler->raw($this->specialVars[$name]);
} elseif ($this->getAttribute('always_defined')) {
$compiler
->raw('$context[')
->string($name)
->raw(']')
;
} else {
if (version_compare(phpversion(),'5.4.0RC1','>=')) {
$compiler
->raw('(isset($context[')
->string($name)
->raw(']) ? $context[')
->string($name)
->raw('] : ')
;
if ($this->getAttribute('ignore_strict_check') || !$compiler->getEnvironment()->isStrictVariables()) {
$compiler->raw('null)');
} else {
$compiler->raw('$this->getContext($context, ')->string($name)->raw('))');
}
} else {
$compiler
->raw('$this->getContext($context, ')
->string($name)
;
if ($this->getAttribute('ignore_strict_check')) {
$compiler->raw(', true');
}
$compiler
->raw(')')
;
}
}
}
public function isSpecial()
{
return isset($this->specialVars[$this->getAttribute('name')]);
}
public function isSimple()
{
return !$this->isSpecial() && !$this->getAttribute('is_defined_test');
}
}
}
namespace
{
class Twig_Node_Expression_AssignName extends Twig_Node_Expression_Name
{
public function compile(Twig_Compiler $compiler)
{
$compiler
->raw('$context[')
->string($this->getAttribute('name'))
->raw(']')
;
}
}
}
namespace
{
abstract class Twig_Node_Expression_Binary extends Twig_Node_Expression
{
public function __construct(Twig_NodeInterface $left, Twig_NodeInterface $right, $lineno)
{
parent::__construct(array('left'=> $left,'right'=> $right), array(), $lineno);
}
public function compile(Twig_Compiler $compiler)
{
$compiler
->raw('(')
->subcompile($this->getNode('left'))
->raw(' ')
;
$this->operator($compiler);
$compiler
->raw(' ')
->subcompile($this->getNode('right'))
->raw(')')
;
}
abstract public function operator(Twig_Compiler $compiler);
}
}
namespace
{
class Twig_Node_Expression_Binary_Add extends Twig_Node_Expression_Binary
{
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('+');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_And extends Twig_Node_Expression_Binary
{
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('&&');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_BitwiseAnd extends Twig_Node_Expression_Binary
{
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('&');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_BitwiseOr extends Twig_Node_Expression_Binary
{
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('|');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_BitwiseXor extends Twig_Node_Expression_Binary
{
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('^');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_Concat extends Twig_Node_Expression_Binary
{
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('.');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_Div extends Twig_Node_Expression_Binary
{
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('/');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_Equal extends Twig_Node_Expression_Binary
{
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('==');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_FloorDiv extends Twig_Node_Expression_Binary
{
public function compile(Twig_Compiler $compiler)
{
$compiler->raw('intval(floor(');
parent::compile($compiler);
$compiler->raw('))');
}
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('/');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_Greater extends Twig_Node_Expression_Binary
{
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('>');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_GreaterEqual extends Twig_Node_Expression_Binary
{
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('>=');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_In extends Twig_Node_Expression_Binary
{
public function compile(Twig_Compiler $compiler)
{
$compiler
->raw('twig_in_filter(')
->subcompile($this->getNode('left'))
->raw(', ')
->subcompile($this->getNode('right'))
->raw(')')
;
}
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('in');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_Less extends Twig_Node_Expression_Binary
{
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('<');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_LessEqual extends Twig_Node_Expression_Binary
{
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('<=');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_Mod extends Twig_Node_Expression_Binary
{
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('%');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_Mul extends Twig_Node_Expression_Binary
{
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('*');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_NotEqual extends Twig_Node_Expression_Binary
{
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('!=');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_NotIn extends Twig_Node_Expression_Binary
{
public function compile(Twig_Compiler $compiler)
{
$compiler
->raw('!twig_in_filter(')
->subcompile($this->getNode('left'))
->raw(', ')
->subcompile($this->getNode('right'))
->raw(')')
;
}
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('not in');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_Or extends Twig_Node_Expression_Binary
{
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('||');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_Power extends Twig_Node_Expression_Binary
{
public function compile(Twig_Compiler $compiler)
{
$compiler
->raw('pow(')
->subcompile($this->getNode('left'))
->raw(', ')
->subcompile($this->getNode('right'))
->raw(')')
;
}
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('**');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_Range extends Twig_Node_Expression_Binary
{
public function compile(Twig_Compiler $compiler)
{
$compiler
->raw('range(')
->subcompile($this->getNode('left'))
->raw(', ')
->subcompile($this->getNode('right'))
->raw(')')
;
}
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('..');
}
}
}
namespace
{
class Twig_Node_Expression_Binary_Sub extends Twig_Node_Expression_Binary
{
public function operator(Twig_Compiler $compiler)
{
return $compiler->raw('-');
}
}
}
namespace
{
class Twig_Node_Expression_BlockReference extends Twig_Node_Expression
{
public function __construct(Twig_NodeInterface $name, $asString = false, $lineno, $tag = null)
{
parent::__construct(array('name'=> $name), array('as_string'=> $asString,'output'=> false), $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
if ($this->getAttribute('as_string')) {
$compiler->raw('(string) ');
}
if ($this->getAttribute('output')) {
$compiler
->addDebugInfo($this)
->write("\$this->displayBlock(")
->subcompile($this->getNode('name'))
->raw(", \$context, \$blocks);\n")
;
} else {
$compiler
->raw("\$this->renderBlock(")
->subcompile($this->getNode('name'))
->raw(", \$context, \$blocks)")
;
}
}
}
}
namespace
{
class Twig_Node_Expression_Conditional extends Twig_Node_Expression
{
public function __construct(Twig_Node_Expression $expr1, Twig_Node_Expression $expr2, Twig_Node_Expression $expr3, $lineno)
{
parent::__construct(array('expr1'=> $expr1,'expr2'=> $expr2,'expr3'=> $expr3), array(), $lineno);
}
public function compile(Twig_Compiler $compiler)
{
$compiler
->raw('((')
->subcompile($this->getNode('expr1'))
->raw(') ? (')
->subcompile($this->getNode('expr2'))
->raw(') : (')
->subcompile($this->getNode('expr3'))
->raw('))')
;
}
}
}
namespace
{
class Twig_Node_Expression_Constant extends Twig_Node_Expression
{
public function __construct($value, $lineno)
{
parent::__construct(array(), array('value'=> $value), $lineno);
}
public function compile(Twig_Compiler $compiler)
{
$compiler->repr($this->getAttribute('value'));
}
}
}
namespace
{
class Twig_Node_Expression_ExtensionReference extends Twig_Node_Expression
{
public function __construct($name, $lineno, $tag = null)
{
parent::__construct(array(), array('name'=> $name), $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
$compiler->raw(sprintf("\$this->env->getExtension('%s')", $this->getAttribute('name')));
}
}
}
namespace
{
abstract class Twig_Node_Expression_Call extends Twig_Node_Expression
{
protected function compileCallable(Twig_Compiler $compiler)
{
$callable = $this->getAttribute('callable');
$closingParenthesis = false;
if ($callable) {
if (is_string($callable)) {
$compiler->raw($callable);
} elseif (is_array($callable) && $callable[0] instanceof Twig_ExtensionInterface) {
$compiler->raw(sprintf('$this->env->getExtension(\'%s\')->%s', $callable[0]->getName(), $callable[1]));
} else {
$type = ucfirst($this->getAttribute('type'));
$compiler->raw(sprintf('call_user_func_array($this->env->get%s(\'%s\')->getCallable(), array', $type, $this->getAttribute('name')));
$closingParenthesis = true;
}
} else {
$compiler->raw($this->getAttribute('thing')->compile());
}
$this->compileArguments($compiler);
if ($closingParenthesis) {
$compiler->raw(')');
}
}
protected function compileArguments(Twig_Compiler $compiler)
{
$compiler->raw('(');
$first = true;
if ($this->hasAttribute('needs_environment') && $this->getAttribute('needs_environment')) {
$compiler->raw('$this->env');
$first = false;
}
if ($this->hasAttribute('needs_context') && $this->getAttribute('needs_context')) {
if (!$first) {
$compiler->raw(', ');
}
$compiler->raw('$context');
$first = false;
}
if ($this->hasAttribute('arguments')) {
foreach ($this->getAttribute('arguments') as $argument) {
if (!$first) {
$compiler->raw(', ');
}
$compiler->string($argument);
$first = false;
}
}
if ($this->hasNode('node')) {
if (!$first) {
$compiler->raw(', ');
}
$compiler->subcompile($this->getNode('node'));
$first = false;
}
if ($this->hasNode('arguments') && null !== $this->getNode('arguments')) {
$callable = $this->hasAttribute('callable') ? $this->getAttribute('callable') : null;
$arguments = $this->getArguments($callable, $this->getNode('arguments'));
foreach ($arguments as $node) {
if (!$first) {
$compiler->raw(', ');
}
$compiler->subcompile($node);
$first = false;
}
}
$compiler->raw(')');
}
protected function getArguments($callable, $arguments)
{
$parameters = array();
$named = false;
foreach ($arguments as $name => $node) {
if (!is_int($name)) {
$named = true;
$name = $this->normalizeName($name);
} elseif ($named) {
throw new Twig_Error_Syntax(sprintf('Positional arguments cannot be used after named arguments for %s "%s".', $this->getAttribute('type'), $this->getAttribute('name')));
}
$parameters[$name] = $node;
}
if (!$named) {
return $parameters;
}
if (!$callable) {
throw new LogicException(sprintf('Named arguments are not supported for %s "%s".', $this->getAttribute('type'), $this->getAttribute('name')));
}
if (is_array($callable)) {
$r = new ReflectionMethod($callable[0], $callable[1]);
} elseif (is_object($callable) && !$callable instanceof Closure) {
$r = new ReflectionObject($callable);
$r = $r->getMethod('__invoke');
} else {
$r = new ReflectionFunction($callable);
}
$definition = $r->getParameters();
if ($this->hasNode('node')) {
array_shift($definition);
}
if ($this->hasAttribute('needs_environment') && $this->getAttribute('needs_environment')) {
array_shift($definition);
}
if ($this->hasAttribute('needs_context') && $this->getAttribute('needs_context')) {
array_shift($definition);
}
if ($this->hasAttribute('arguments') && null !== $this->getAttribute('arguments')) {
foreach ($this->getAttribute('arguments') as $argument) {
array_shift($definition);
}
}
$arguments = array();
$pos = 0;
foreach ($definition as $param) {
$name = $this->normalizeName($param->name);
if (array_key_exists($name, $parameters)) {
if (array_key_exists($pos, $parameters)) {
throw new Twig_Error_Syntax(sprintf('Arguments "%s" is defined twice for %s "%s".', $name, $this->getAttribute('type'), $this->getAttribute('name')));
}
$arguments[] = $parameters[$name];
unset($parameters[$name]);
} elseif (array_key_exists($pos, $parameters)) {
$arguments[] = $parameters[$pos];
unset($parameters[$pos]);
++$pos;
} elseif ($param->isDefaultValueAvailable()) {
$arguments[] = new Twig_Node_Expression_Constant($param->getDefaultValue(), -1);
} elseif ($param->isOptional()) {
break;
} else {
throw new Twig_Error_Syntax(sprintf('Value for argument "%s" is required for %s "%s".', $name, $this->getAttribute('type'), $this->getAttribute('name')));
}
}
foreach (array_keys($parameters) as $name) {
throw new Twig_Error_Syntax(sprintf('Unknown argument "%s" for %s "%s".', $name, $this->getAttribute('type'), $this->getAttribute('name')));
}
return $arguments;
}
protected function normalizeName($name)
{
return strtolower(preg_replace(array('/([A-Z]+)([A-Z][a-z])/','/([a-z\d])([A-Z])/'), array('\\1_\\2','\\1_\\2'), $name));
}
}
}
namespace
{
class Twig_Node_Expression_Filter extends Twig_Node_Expression_Call
{
public function __construct(Twig_NodeInterface $node, Twig_Node_Expression_Constant $filterName, Twig_NodeInterface $arguments, $lineno, $tag = null)
{
parent::__construct(array('node'=> $node,'filter'=> $filterName,'arguments'=> $arguments), array(), $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
$name = $this->getNode('filter')->getAttribute('value');
$filter = $compiler->getEnvironment()->getFilter($name);
$this->setAttribute('name', $name);
$this->setAttribute('type','filter');
$this->setAttribute('thing', $filter);
$this->setAttribute('needs_environment', $filter->needsEnvironment());
$this->setAttribute('needs_context', $filter->needsContext());
$this->setAttribute('arguments', $filter->getArguments());
if ($filter instanceof Twig_FilterCallableInterface || $filter instanceof Twig_SimpleFilter) {
$this->setAttribute('callable', $filter->getCallable());
}
$this->compileCallable($compiler);
}
}
}
namespace
{
class Twig_Node_Expression_Filter_Default extends Twig_Node_Expression_Filter
{
public function __construct(Twig_NodeInterface $node, Twig_Node_Expression_Constant $filterName, Twig_NodeInterface $arguments, $lineno, $tag = null)
{
$default = new Twig_Node_Expression_Filter($node, new Twig_Node_Expression_Constant('default', $node->getLine()), $arguments, $node->getLine());
if ('default'=== $filterName->getAttribute('value') && ($node instanceof Twig_Node_Expression_Name || $node instanceof Twig_Node_Expression_GetAttr)) {
$test = new Twig_Node_Expression_Test_Defined(clone $node,'defined', new Twig_Node(), $node->getLine());
$false = count($arguments) ? $arguments->getNode(0) : new Twig_Node_Expression_Constant('', $node->getLine());
$node = new Twig_Node_Expression_Conditional($test, $default, $false, $node->getLine());
} else {
$node = $default;
}
parent::__construct($node, $filterName, $arguments, $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
$compiler->subcompile($this->getNode('node'));
}
}
}
namespace
{
class Twig_Node_Expression_Function extends Twig_Node_Expression_Call
{
public function __construct($name, Twig_NodeInterface $arguments, $lineno)
{
parent::__construct(array('arguments'=> $arguments), array('name'=> $name), $lineno);
}
public function compile(Twig_Compiler $compiler)
{
$name = $this->getAttribute('name');
$function = $compiler->getEnvironment()->getFunction($name);
$this->setAttribute('name', $name);
$this->setAttribute('type','function');
$this->setAttribute('thing', $function);
$this->setAttribute('needs_environment', $function->needsEnvironment());
$this->setAttribute('needs_context', $function->needsContext());
$this->setAttribute('arguments', $function->getArguments());
if ($function instanceof Twig_FunctionCallableInterface || $function instanceof Twig_SimpleFunction) {
$this->setAttribute('callable', $function->getCallable());
}
$this->compileCallable($compiler);
}
}
}
namespace
{
class Twig_Node_Expression_GetAttr extends Twig_Node_Expression
{
public function __construct(Twig_Node_Expression $node, Twig_Node_Expression $attribute, Twig_Node_Expression_Array $arguments, $type, $lineno)
{
parent::__construct(array('node'=> $node,'attribute'=> $attribute,'arguments'=> $arguments), array('type'=> $type,'is_defined_test'=> false,'ignore_strict_check'=> false,'disable_c_ext'=> false), $lineno);
}
public function compile(Twig_Compiler $compiler)
{
if (function_exists('twig_template_get_attributes') && !$this->getAttribute('disable_c_ext')) {
$compiler->raw('twig_template_get_attributes($this, ');
} else {
$compiler->raw('$this->getAttribute(');
}
if ($this->getAttribute('ignore_strict_check')) {
$this->getNode('node')->setAttribute('ignore_strict_check', true);
}
$compiler->subcompile($this->getNode('node'));
$compiler->raw(', ')->subcompile($this->getNode('attribute'));
if (count($this->getNode('arguments')) || Twig_TemplateInterface::ANY_CALL !== $this->getAttribute('type') || $this->getAttribute('is_defined_test') || $this->getAttribute('ignore_strict_check')) {
$compiler->raw(', ')->subcompile($this->getNode('arguments'));
if (Twig_TemplateInterface::ANY_CALL !== $this->getAttribute('type') || $this->getAttribute('is_defined_test') || $this->getAttribute('ignore_strict_check')) {
$compiler->raw(', ')->repr($this->getAttribute('type'));
}
if ($this->getAttribute('is_defined_test') || $this->getAttribute('ignore_strict_check')) {
$compiler->raw(', '.($this->getAttribute('is_defined_test') ?'true':'false'));
}
if ($this->getAttribute('ignore_strict_check')) {
$compiler->raw(', '.($this->getAttribute('ignore_strict_check') ?'true':'false'));
}
}
$compiler->raw(')');
}
}
}
namespace
{
class Twig_Node_Expression_MethodCall extends Twig_Node_Expression
{
public function __construct(Twig_Node_Expression $node, $method, Twig_Node_Expression_Array $arguments, $lineno)
{
parent::__construct(array('node'=> $node,'arguments'=> $arguments), array('method'=> $method,'safe'=> false), $lineno);
if ($node instanceof Twig_Node_Expression_Name) {
$node->setAttribute('always_defined', true);
}
}
public function compile(Twig_Compiler $compiler)
{
$compiler
->subcompile($this->getNode('node'))
->raw('->')
->raw($this->getAttribute('method'))
->raw('(')
;
$first = true;
foreach ($this->getNode('arguments')->getKeyValuePairs() as $pair) {
if (!$first) {
$compiler->raw(', ');
}
$first = false;
$compiler->subcompile($pair['value']);
}
$compiler->raw(')');
}
}
}
namespace
{
class Twig_Node_Expression_Parent extends Twig_Node_Expression
{
public function __construct($name, $lineno, $tag = null)
{
parent::__construct(array(), array('output'=> false,'name'=> $name), $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
if ($this->getAttribute('output')) {
$compiler
->addDebugInfo($this)
->write("\$this->displayParentBlock(")
->string($this->getAttribute('name'))
->raw(", \$context, \$blocks);\n")
;
} else {
$compiler
->raw("\$this->renderParentBlock(")
->string($this->getAttribute('name'))
->raw(", \$context, \$blocks)")
;
}
}
}
}
namespace
{
class Twig_Node_Expression_TempName extends Twig_Node_Expression
{
public function __construct($name, $lineno)
{
parent::__construct(array(), array('name'=> $name), $lineno);
}
public function compile(Twig_Compiler $compiler)
{
$compiler
->raw('$_')
->raw($this->getAttribute('name'))
->raw('_')
;
}
}
}
namespace
{
class Twig_Node_Expression_Test extends Twig_Node_Expression_Call
{
public function __construct(Twig_NodeInterface $node, $name, Twig_NodeInterface $arguments = null, $lineno)
{
parent::__construct(array('node'=> $node,'arguments'=> $arguments), array('name'=> $name), $lineno);
}
public function compile(Twig_Compiler $compiler)
{
$name = $this->getAttribute('name');
$test = $compiler->getEnvironment()->getTest($name);
$this->setAttribute('name', $name);
$this->setAttribute('type','test');
$this->setAttribute('thing', $test);
if ($test instanceof Twig_TestCallableInterface || $test instanceof Twig_SimpleTest) {
$this->setAttribute('callable', $test->getCallable());
}
$this->compileCallable($compiler);
}
}
}
namespace
{
class Twig_Node_Expression_Test_Constant extends Twig_Node_Expression_Test
{
public function compile(Twig_Compiler $compiler)
{
$compiler
->raw('(')
->subcompile($this->getNode('node'))
->raw(' === constant(')
;
if ($this->getNode('arguments')->hasNode(1)) {
$compiler
->raw('get_class(')
->subcompile($this->getNode('arguments')->getNode(1))
->raw(')."::".')
;
}
$compiler
->subcompile($this->getNode('arguments')->getNode(0))
->raw('))')
;
}
}
}
namespace
{
class Twig_Node_Expression_Test_Defined extends Twig_Node_Expression_Test
{
public function __construct(Twig_NodeInterface $node, $name, Twig_NodeInterface $arguments = null, $lineno)
{
parent::__construct($node, $name, $arguments, $lineno);
if ($node instanceof Twig_Node_Expression_Name) {
$node->setAttribute('is_defined_test', true);
} elseif ($node instanceof Twig_Node_Expression_GetAttr) {
$node->setAttribute('is_defined_test', true);
$this->changeIgnoreStrictCheck($node);
} else {
throw new Twig_Error_Syntax('The "defined" test only works with simple variables', $this->getLine());
}
}
protected function changeIgnoreStrictCheck(Twig_Node_Expression_GetAttr $node)
{
$node->setAttribute('ignore_strict_check', true);
if ($node->getNode('node') instanceof Twig_Node_Expression_GetAttr) {
$this->changeIgnoreStrictCheck($node->getNode('node'));
}
}
public function compile(Twig_Compiler $compiler)
{
$compiler->subcompile($this->getNode('node'));
}
}
}
namespace
{
class Twig_Node_Expression_Test_Divisibleby extends Twig_Node_Expression_Test
{
public function compile(Twig_Compiler $compiler)
{
$compiler
->raw('(0 == ')
->subcompile($this->getNode('node'))
->raw(' % ')
->subcompile($this->getNode('arguments')->getNode(0))
->raw(')')
;
}
}
}
namespace
{
class Twig_Node_Expression_Test_Even extends Twig_Node_Expression_Test
{
public function compile(Twig_Compiler $compiler)
{
$compiler
->raw('(')
->subcompile($this->getNode('node'))
->raw(' % 2 == 0')
->raw(')')
;
}
}
}
namespace
{
class Twig_Node_Expression_Test_Null extends Twig_Node_Expression_Test
{
public function compile(Twig_Compiler $compiler)
{
$compiler
->raw('(null === ')
->subcompile($this->getNode('node'))
->raw(')')
;
}
}
}
namespace
{
class Twig_Node_Expression_Test_Odd extends Twig_Node_Expression_Test
{
public function compile(Twig_Compiler $compiler)
{
$compiler
->raw('(')
->subcompile($this->getNode('node'))
->raw(' % 2 == 1')
->raw(')')
;
}
}
}
namespace
{
class Twig_Node_Expression_Test_Sameas extends Twig_Node_Expression_Test
{
public function compile(Twig_Compiler $compiler)
{
$compiler
->raw('(')
->subcompile($this->getNode('node'))
->raw(' === ')
->subcompile($this->getNode('arguments')->getNode(0))
->raw(')')
;
}
}
}
namespace
{
abstract class Twig_Node_Expression_Unary extends Twig_Node_Expression
{
public function __construct(Twig_NodeInterface $node, $lineno)
{
parent::__construct(array('node'=> $node), array(), $lineno);
}
public function compile(Twig_Compiler $compiler)
{
$compiler->raw('(');
$this->operator($compiler);
$compiler
->subcompile($this->getNode('node'))
->raw(')')
;
}
abstract public function operator(Twig_Compiler $compiler);
}
}
namespace
{
class Twig_Node_Expression_Unary_Neg extends Twig_Node_Expression_Unary
{
public function operator(Twig_Compiler $compiler)
{
$compiler->raw('-');
}
}
}
namespace
{
class Twig_Node_Expression_Unary_Not extends Twig_Node_Expression_Unary
{
public function operator(Twig_Compiler $compiler)
{
$compiler->raw('!');
}
}
}
namespace
{
class Twig_Node_Expression_Unary_Pos extends Twig_Node_Expression_Unary
{
public function operator(Twig_Compiler $compiler)
{
$compiler->raw('+');
}
}
}
namespace
{
class Twig_Node_Flush extends Twig_Node
{
public function __construct($lineno, $tag)
{
parent::__construct(array(), array(), $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
$compiler
->addDebugInfo($this)
->write("flush();\n")
;
}
}
}
namespace
{
class Twig_Node_For extends Twig_Node
{
protected $loop;
public function __construct(Twig_Node_Expression_AssignName $keyTarget, Twig_Node_Expression_AssignName $valueTarget, Twig_Node_Expression $seq, Twig_Node_Expression $ifexpr = null, Twig_NodeInterface $body, Twig_NodeInterface $else = null, $lineno, $tag = null)
{
$body = new Twig_Node(array($body, $this->loop = new Twig_Node_ForLoop($lineno, $tag)));
if (null !== $ifexpr) {
$body = new Twig_Node_If(new Twig_Node(array($ifexpr, $body)), null, $lineno, $tag);
}
parent::__construct(array('key_target'=> $keyTarget,'value_target'=> $valueTarget,'seq'=> $seq,'body'=> $body,'else'=> $else), array('with_loop'=> true,'ifexpr'=> null !== $ifexpr), $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
$compiler
->addDebugInfo($this)
->write("\$context['_parent'] = (array) \$context;\n")
->write("\$context['_seq'] = twig_ensure_traversable(")
->subcompile($this->getNode('seq'))
->raw(");\n")
;
if (null !== $this->getNode('else')) {
$compiler->write("\$context['_iterated'] = false;\n");
}
if ($this->getAttribute('with_loop')) {
$compiler
->write("\$context['loop'] = array(\n")
->write("  'parent' => \$context['_parent'],\n")
->write("  'index0' => 0,\n")
->write("  'index'  => 1,\n")
->write("  'first'  => true,\n")
->write(");\n")
;
if (!$this->getAttribute('ifexpr')) {
$compiler
->write("if (is_array(\$context['_seq']) || (is_object(\$context['_seq']) && \$context['_seq'] instanceof Countable)) {\n")
->indent()
->write("\$length = count(\$context['_seq']);\n")
->write("\$context['loop']['revindex0'] = \$length - 1;\n")
->write("\$context['loop']['revindex'] = \$length;\n")
->write("\$context['loop']['length'] = \$length;\n")
->write("\$context['loop']['last'] = 1 === \$length;\n")
->outdent()
->write("}\n")
;
}
}
$this->loop->setAttribute('else', null !== $this->getNode('else'));
$this->loop->setAttribute('with_loop', $this->getAttribute('with_loop'));
$this->loop->setAttribute('ifexpr', $this->getAttribute('ifexpr'));
$compiler
->write("foreach (\$context['_seq'] as ")
->subcompile($this->getNode('key_target'))
->raw(" => ")
->subcompile($this->getNode('value_target'))
->raw(") {\n")
->indent()
->subcompile($this->getNode('body'))
->outdent()
->write("}\n")
;
if (null !== $this->getNode('else')) {
$compiler
->write("if (!\$context['_iterated']) {\n")
->indent()
->subcompile($this->getNode('else'))
->outdent()
->write("}\n")
;
}
$compiler->write("\$_parent = \$context['_parent'];\n");
$compiler->write('unset($context[\'_seq\'], $context[\'_iterated\'], $context[\''.$this->getNode('key_target')->getAttribute('name').'\'], $context[\''.$this->getNode('value_target')->getAttribute('name').'\'], $context[\'_parent\'], $context[\'loop\']);'."\n");
$compiler->write("\$context = array_intersect_key(\$context, \$_parent) + \$_parent;\n");
}
}
}
namespace
{
class Twig_Node_ForLoop extends Twig_Node
{
public function __construct($lineno, $tag = null)
{
parent::__construct(array(), array('with_loop'=> false,'ifexpr'=> false,'else'=> false), $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
if ($this->getAttribute('else')) {
$compiler->write("\$context['_iterated'] = true;\n");
}
if ($this->getAttribute('with_loop')) {
$compiler
->write("++\$context['loop']['index0'];\n")
->write("++\$context['loop']['index'];\n")
->write("\$context['loop']['first'] = false;\n")
;
if (!$this->getAttribute('ifexpr')) {
$compiler
->write("if (isset(\$context['loop']['length'])) {\n")
->indent()
->write("--\$context['loop']['revindex0'];\n")
->write("--\$context['loop']['revindex'];\n")
->write("\$context['loop']['last'] = 0 === \$context['loop']['revindex0'];\n")
->outdent()
->write("}\n")
;
}
}
}
}
}
namespace
{
class Twig_Node_If extends Twig_Node
{
public function __construct(Twig_NodeInterface $tests, Twig_NodeInterface $else = null, $lineno, $tag = null)
{
parent::__construct(array('tests'=> $tests,'else'=> $else), array(), $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
$compiler->addDebugInfo($this);
for ($i = 0; $i < count($this->getNode('tests')); $i += 2) {
if ($i > 0) {
$compiler
->outdent()
->write("} elseif (")
;
} else {
$compiler
->write('if (')
;
}
$compiler
->subcompile($this->getNode('tests')->getNode($i))
->raw(") {\n")
->indent()
->subcompile($this->getNode('tests')->getNode($i + 1))
;
}
if ($this->hasNode('else') && null !== $this->getNode('else')) {
$compiler
->outdent()
->write("} else {\n")
->indent()
->subcompile($this->getNode('else'))
;
}
$compiler
->outdent()
->write("}\n");
}
}
}
namespace
{
class Twig_Node_Import extends Twig_Node
{
public function __construct(Twig_Node_Expression $expr, Twig_Node_Expression $var, $lineno, $tag = null)
{
parent::__construct(array('expr'=> $expr,'var'=> $var), array(), $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
$compiler
->addDebugInfo($this)
->write('')
->subcompile($this->getNode('var'))
->raw(' = ')
;
if ($this->getNode('expr') instanceof Twig_Node_Expression_Name &&'_self'=== $this->getNode('expr')->getAttribute('name')) {
$compiler->raw("\$this");
} else {
$compiler
->raw('$this->env->loadTemplate(')
->subcompile($this->getNode('expr'))
->raw(")")
;
}
$compiler->raw(";\n");
}
}
}
namespace
{
class Twig_Node_Macro extends Twig_Node
{
public function __construct($name, Twig_NodeInterface $body, Twig_NodeInterface $arguments, $lineno, $tag = null)
{
parent::__construct(array('body'=> $body,'arguments'=> $arguments), array('name'=> $name), $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
$compiler
->addDebugInfo($this)
->write(sprintf("public function get%s(", $this->getAttribute('name')))
;
$count = count($this->getNode('arguments'));
$pos = 0;
foreach ($this->getNode('arguments') as $name => $default) {
$compiler
->raw('$_'.$name.' = ')
->subcompile($default)
;
if (++$pos < $count) {
$compiler->raw(', ');
}
}
$compiler
->raw(")\n")
->write("{\n")
->indent()
;
if (!count($this->getNode('arguments'))) {
$compiler->write("\$context = \$this->env->getGlobals();\n\n");
} else {
$compiler
->write("\$context = \$this->env->mergeGlobals(array(\n")
->indent()
;
foreach ($this->getNode('arguments') as $name => $default) {
$compiler
->write('')
->string($name)
->raw(' => $_'.$name)
->raw(",\n")
;
}
$compiler
->outdent()
->write("));\n\n")
;
}
$compiler
->write("\$blocks = array();\n\n")
->write("ob_start();\n")
->write("try {\n")
->indent()
->subcompile($this->getNode('body'))
->outdent()
->write("} catch (Exception \$e) {\n")
->indent()
->write("ob_end_clean();\n\n")
->write("throw \$e;\n")
->outdent()
->write("}\n\n")
->write("return ('' === \$tmp = ob_get_clean()) ? '' : new Twig_Markup(\$tmp, \$this->env->getCharset());\n")
->outdent()
->write("}\n\n")
;
}
}
}
namespace
{
class Twig_Node_Module extends Twig_Node
{
public function __construct(Twig_NodeInterface $body, Twig_Node_Expression $parent = null, Twig_NodeInterface $blocks, Twig_NodeInterface $macros, Twig_NodeInterface $traits, $embeddedTemplates, $filename)
{
parent::__construct(array('parent'=> $parent,'body'=> $body,'blocks'=> $blocks,'macros'=> $macros,'traits'=> $traits), array('filename'=> $filename,'index'=> null,'embedded_templates'=> $embeddedTemplates), 1);
}
public function setIndex($index)
{
$this->setAttribute('index', $index);
}
public function compile(Twig_Compiler $compiler)
{
$this->compileTemplate($compiler);
foreach ($this->getAttribute('embedded_templates') as $template) {
$compiler->subcompile($template);
}
}
protected function compileTemplate(Twig_Compiler $compiler)
{
if (!$this->getAttribute('index')) {
$compiler->write('');
}
$this->compileClassHeader($compiler);
if (count($this->getNode('blocks')) || count($this->getNode('traits')) || null === $this->getNode('parent') || $this->getNode('parent') instanceof Twig_Node_Expression_Constant) {
$this->compileConstructor($compiler);
}
$this->compileGetParent($compiler);
$this->compileDisplayHeader($compiler);
$this->compileDisplayBody($compiler);
$this->compileDisplayFooter($compiler);
$compiler->subcompile($this->getNode('blocks'));
$this->compileMacros($compiler);
$this->compileGetTemplateName($compiler);
$this->compileIsTraitable($compiler);
$this->compileDebugInfo($compiler);
$this->compileClassFooter($compiler);
}
protected function compileGetParent(Twig_Compiler $compiler)
{
if (null === $this->getNode('parent')) {
return;
}
$compiler
->write("protected function doGetParent(array \$context)\n","{\n")
->indent()
->write("return ")
;
if ($this->getNode('parent') instanceof Twig_Node_Expression_Constant) {
$compiler->subcompile($this->getNode('parent'));
} else {
$compiler
->raw("\$this->env->resolveTemplate(")
->subcompile($this->getNode('parent'))
->raw(")")
;
}
$compiler
->raw(";\n")
->outdent()
->write("}\n\n")
;
}
protected function compileDisplayBody(Twig_Compiler $compiler)
{
$compiler->subcompile($this->getNode('body'));
if (null !== $this->getNode('parent')) {
if ($this->getNode('parent') instanceof Twig_Node_Expression_Constant) {
$compiler->write("\$this->parent");
} else {
$compiler->write("\$this->getParent(\$context)");
}
$compiler->raw("->display(\$context, array_merge(\$this->blocks, \$blocks));\n");
}
}
protected function compileClassHeader(Twig_Compiler $compiler)
{
$compiler
->write("\n\n")
->write("/* ".str_replace('*/','* /', $this->getAttribute('filename'))." */\n")
->write('class '.$compiler->getEnvironment()->getTemplateClass($this->getAttribute('filename'), $this->getAttribute('index')))
->raw(sprintf(" extends %s\n", $compiler->getEnvironment()->getBaseTemplateClass()))
->write("{\n")
->indent()
;
}
protected function compileConstructor(Twig_Compiler $compiler)
{
$compiler
->write("public function __construct(Twig_Environment \$env)\n","{\n")
->indent()
->write("parent::__construct(\$env);\n\n")
;
if (null === $this->getNode('parent')) {
$compiler->write("\$this->parent = false;\n\n");
} elseif ($this->getNode('parent') instanceof Twig_Node_Expression_Constant) {
$compiler
->write("\$this->parent = \$this->env->loadTemplate(")
->subcompile($this->getNode('parent'))
->raw(");\n\n")
;
}
$countTraits = count($this->getNode('traits'));
if ($countTraits) {
foreach ($this->getNode('traits') as $i => $trait) {
$this->compileLoadTemplate($compiler, $trait->getNode('template'), sprintf('$_trait_%s', $i));
$compiler
->addDebugInfo($trait->getNode('template'))
->write(sprintf("if (!\$_trait_%s->isTraitable()) {\n", $i))
->indent()
->write("throw new Twig_Error_Runtime('Template \"'.")
->subcompile($trait->getNode('template'))
->raw(".'\" cannot be used as a trait.');\n")
->outdent()
->write("}\n")
->write(sprintf("\$_trait_%s_blocks = \$_trait_%s->getBlocks();\n\n", $i, $i))
;
foreach ($trait->getNode('targets') as $key => $value) {
$compiler
->write(sprintf("\$_trait_%s_blocks[", $i))
->subcompile($value)
->raw(sprintf("] = \$_trait_%s_blocks[", $i))
->string($key)
->raw(sprintf("]; unset(\$_trait_%s_blocks[", $i))
->string($key)
->raw("]);\n\n")
;
}
}
if ($countTraits > 1) {
$compiler
->write("\$this->traits = array_merge(\n")
->indent()
;
for ($i = 0; $i < $countTraits; $i++) {
$compiler
->write(sprintf("\$_trait_%s_blocks".($i == $countTraits - 1 ?'':',')."\n", $i))
;
}
$compiler
->outdent()
->write(");\n\n")
;
} else {
$compiler
->write("\$this->traits = \$_trait_0_blocks;\n\n")
;
}
$compiler
->write("\$this->blocks = array_merge(\n")
->indent()
->write("\$this->traits,\n")
->write("array(\n")
;
} else {
$compiler
->write("\$this->blocks = array(\n")
;
}
$compiler
->indent()
;
foreach ($this->getNode('blocks') as $name => $node) {
$compiler
->write(sprintf("'%s' => array(\$this, 'block_%s'),\n", $name, $name))
;
}
if ($countTraits) {
$compiler
->outdent()
->write(")\n")
;
}
$compiler
->outdent()
->write(");\n")
->outdent()
->write("}\n\n");
;
}
protected function compileDisplayHeader(Twig_Compiler $compiler)
{
$compiler
->write("protected function doDisplay(array \$context, array \$blocks = array())\n","{\n")
->indent()
;
}
protected function compileDisplayFooter(Twig_Compiler $compiler)
{
$compiler
->outdent()
->write("}\n\n")
;
}
protected function compileClassFooter(Twig_Compiler $compiler)
{
$compiler
->outdent()
->write("}\n")
;
}
protected function compileMacros(Twig_Compiler $compiler)
{
$compiler->subcompile($this->getNode('macros'));
}
protected function compileGetTemplateName(Twig_Compiler $compiler)
{
$compiler
->write("public function getTemplateName()\n","{\n")
->indent()
->write('return ')
->repr($this->getAttribute('filename'))
->raw(";\n")
->outdent()
->write("}\n\n")
;
}
protected function compileIsTraitable(Twig_Compiler $compiler)
{
$traitable = null === $this->getNode('parent') && 0 === count($this->getNode('macros'));
if ($traitable) {
if ($this->getNode('body') instanceof Twig_Node_Body) {
$nodes = $this->getNode('body')->getNode(0);
} else {
$nodes = $this->getNode('body');
}
if (!count($nodes)) {
$nodes = new Twig_Node(array($nodes));
}
foreach ($nodes as $node) {
if (!count($node)) {
continue;
}
if ($node instanceof Twig_Node_Text && ctype_space($node->getAttribute('data'))) {
continue;
}
if ($node instanceof Twig_Node_BlockReference) {
continue;
}
$traitable = false;
break;
}
}
if ($traitable) {
return;
}
$compiler
->write("public function isTraitable()\n","{\n")
->indent()
->write(sprintf("return %s;\n", $traitable ?'true':'false'))
->outdent()
->write("}\n\n")
;
}
protected function compileDebugInfo(Twig_Compiler $compiler)
{
$compiler
->write("public function getDebugInfo()\n","{\n")
->indent()
->write(sprintf("return %s;\n", str_replace("\n",'', var_export(array_reverse($compiler->getDebugInfo(), true), true))))
->outdent()
->write("}\n")
;
}
protected function compileLoadTemplate(Twig_Compiler $compiler, $node, $var)
{
if ($node instanceof Twig_Node_Expression_Constant) {
$compiler
->write(sprintf("%s = \$this->env->loadTemplate(", $var))
->subcompile($node)
->raw(");\n")
;
} else {
$compiler
->write(sprintf("%s = ", $var))
->subcompile($node)
->raw(";\n")
->write(sprintf("if (!%s", $var))
->raw(" instanceof Twig_Template) {\n")
->indent()
->write(sprintf("%s = \$this->env->loadTemplate(%s);\n", $var, $var))
->outdent()
->write("}\n")
;
}
}
}
}
namespace
{
class Twig_Node_Print extends Twig_Node implements Twig_NodeOutputInterface
{
public function __construct(Twig_Node_Expression $expr, $lineno, $tag = null)
{
parent::__construct(array('expr'=> $expr), array(), $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
$compiler
->addDebugInfo($this)
->write('echo ')
->subcompile($this->getNode('expr'))
->raw(";\n")
;
}
}
}
namespace
{
class Twig_Node_Sandbox extends Twig_Node
{
public function __construct(Twig_NodeInterface $body, $lineno, $tag = null)
{
parent::__construct(array('body'=> $body), array(), $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
$compiler
->addDebugInfo($this)
->write("\$sandbox = \$this->env->getExtension('sandbox');\n")
->write("if (!\$alreadySandboxed = \$sandbox->isSandboxed()) {\n")
->indent()
->write("\$sandbox->enableSandbox();\n")
->outdent()
->write("}\n")
->subcompile($this->getNode('body'))
->write("if (!\$alreadySandboxed) {\n")
->indent()
->write("\$sandbox->disableSandbox();\n")
->outdent()
->write("}\n")
;
}
}
}
namespace
{
class Twig_Node_SandboxedModule extends Twig_Node_Module
{
protected $usedFilters;
protected $usedTags;
protected $usedFunctions;
public function __construct(Twig_Node_Module $node, array $usedFilters, array $usedTags, array $usedFunctions)
{
parent::__construct($node->getNode('body'), $node->getNode('parent'), $node->getNode('blocks'), $node->getNode('macros'), $node->getNode('traits'), $node->getAttribute('embedded_templates'), $node->getAttribute('filename'), $node->getLine(), $node->getNodeTag());
$this->setAttribute('index', $node->getAttribute('index'));
$this->usedFilters = $usedFilters;
$this->usedTags = $usedTags;
$this->usedFunctions = $usedFunctions;
}
protected function compileDisplayBody(Twig_Compiler $compiler)
{
$compiler->write("\$this->checkSecurity();\n");
parent::compileDisplayBody($compiler);
}
protected function compileDisplayFooter(Twig_Compiler $compiler)
{
parent::compileDisplayFooter($compiler);
$compiler
->write("protected function checkSecurity()\n","{\n")
->indent()
->write("\$this->env->getExtension('sandbox')->checkSecurity(\n")
->indent()
->write(!$this->usedTags ?"array(),\n":"array('".implode('\', \'', $this->usedTags)."'),\n")
->write(!$this->usedFilters ?"array(),\n":"array('".implode('\', \'', $this->usedFilters)."'),\n")
->write(!$this->usedFunctions ?"array()\n":"array('".implode('\', \'', $this->usedFunctions)."')\n")
->outdent()
->write(");\n")
->outdent()
->write("}\n\n")
;
}
}
}
namespace
{
class Twig_Node_SandboxedPrint extends Twig_Node_Print
{
public function __construct(Twig_Node_Expression $expr, $lineno, $tag = null)
{
parent::__construct($expr, $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
$compiler
->addDebugInfo($this)
->write('echo $this->env->getExtension(\'sandbox\')->ensureToStringAllowed(')
->subcompile($this->getNode('expr'))
->raw(");\n")
;
}
protected function removeNodeFilter($node)
{
if ($node instanceof Twig_Node_Expression_Filter) {
return $this->removeNodeFilter($node->getNode('node'));
}
return $node;
}
}
}
namespace
{
class Twig_Node_Set extends Twig_Node
{
public function __construct($capture, Twig_NodeInterface $names, Twig_NodeInterface $values, $lineno, $tag = null)
{
parent::__construct(array('names'=> $names,'values'=> $values), array('capture'=> $capture,'safe'=> false), $lineno, $tag);
if ($this->getAttribute('capture')) {
$this->setAttribute('safe', true);
$values = $this->getNode('values');
if ($values instanceof Twig_Node_Text) {
$this->setNode('values', new Twig_Node_Expression_Constant($values->getAttribute('data'), $values->getLine()));
$this->setAttribute('capture', false);
}
}
}
public function compile(Twig_Compiler $compiler)
{
$compiler->addDebugInfo($this);
if (count($this->getNode('names')) > 1) {
$compiler->write('list(');
foreach ($this->getNode('names') as $idx => $node) {
if ($idx) {
$compiler->raw(', ');
}
$compiler->subcompile($node);
}
$compiler->raw(')');
} else {
if ($this->getAttribute('capture')) {
$compiler
->write("ob_start();\n")
->subcompile($this->getNode('values'))
;
}
$compiler->subcompile($this->getNode('names'), false);
if ($this->getAttribute('capture')) {
$compiler->raw(" = ('' === \$tmp = ob_get_clean()) ? '' : new Twig_Markup(\$tmp, \$this->env->getCharset())");
}
}
if (!$this->getAttribute('capture')) {
$compiler->raw(' = ');
if (count($this->getNode('names')) > 1) {
$compiler->write('array(');
foreach ($this->getNode('values') as $idx => $value) {
if ($idx) {
$compiler->raw(', ');
}
$compiler->subcompile($value);
}
$compiler->raw(')');
} else {
if ($this->getAttribute('safe')) {
$compiler
->raw("('' === \$tmp = ")
->subcompile($this->getNode('values'))
->raw(") ? '' : new Twig_Markup(\$tmp, \$this->env->getCharset())")
;
} else {
$compiler->subcompile($this->getNode('values'));
}
}
}
$compiler->raw(";\n");
}
}
}
namespace
{
class Twig_Node_SetTemp extends Twig_Node
{
public function __construct($name, $lineno)
{
parent::__construct(array(), array('name'=> $name), $lineno);
}
public function compile(Twig_Compiler $compiler)
{
$name = $this->getAttribute('name');
$compiler
->addDebugInfo($this)
->write('if (isset($context[')
->string($name)
->raw('])) { $_')
->raw($name)
->raw('_ = $context[')
->repr($name)
->raw(']; } else { $_')
->raw($name)
->raw("_ = null; }\n")
;
}
}
}
namespace
{
class Twig_Node_Spaceless extends Twig_Node
{
public function __construct(Twig_NodeInterface $body, $lineno, $tag ='spaceless')
{
parent::__construct(array('body'=> $body), array(), $lineno, $tag);
}
public function compile(Twig_Compiler $compiler)
{
$compiler
->addDebugInfo($this)
->write("ob_start();\n")
->subcompile($this->getNode('body'))
->write("echo trim(preg_replace('/>\s+</', '><', ob_get_clean()));\n")
;
}
}
}
namespace
{
class Twig_Node_Text extends Twig_Node implements Twig_NodeOutputInterface
{
public function __construct($data, $lineno)
{
parent::__construct(array(), array('data'=> $data), $lineno);
}
public function compile(Twig_Compiler $compiler)
{
$compiler
->addDebugInfo($this)
->write('echo ')
->string($this->getAttribute('data'))
->raw(";\n")
;
}
}
}
namespace
{
class Twig_NodeTraverser
{
protected $env;
protected $visitors;
public function __construct(Twig_Environment $env, array $visitors = array())
{
$this->env = $env;
$this->visitors = array();
foreach ($visitors as $visitor) {
$this->addVisitor($visitor);
}
}
public function addVisitor(Twig_NodeVisitorInterface $visitor)
{
if (!isset($this->visitors[$visitor->getPriority()])) {
$this->visitors[$visitor->getPriority()] = array();
}
$this->visitors[$visitor->getPriority()][] = $visitor;
}
public function traverse(Twig_NodeInterface $node)
{
ksort($this->visitors);
foreach ($this->visitors as $visitors) {
foreach ($visitors as $visitor) {
$node = $this->traverseForVisitor($visitor, $node);
}
}
return $node;
}
protected function traverseForVisitor(Twig_NodeVisitorInterface $visitor, Twig_NodeInterface $node = null)
{
if (null === $node) {
return null;
}
$node = $visitor->enterNode($node, $this->env);
foreach ($node as $k => $n) {
if (false !== $n = $this->traverseForVisitor($visitor, $n)) {
$node->setNode($k, $n);
} else {
$node->removeNode($k);
}
}
return $visitor->leaveNode($node, $this->env);
}
}
}
namespace
{
interface Twig_NodeVisitorInterface
{
public function enterNode(Twig_NodeInterface $node, Twig_Environment $env);
public function leaveNode(Twig_NodeInterface $node, Twig_Environment $env);
public function getPriority();
}
}
namespace
{
class Twig_NodeVisitor_Escaper implements Twig_NodeVisitorInterface
{
protected $statusStack = array();
protected $blocks = array();
protected $safeAnalysis;
protected $traverser;
protected $defaultStrategy = false;
protected $safeVars = array();
public function __construct()
{
$this->safeAnalysis = new Twig_NodeVisitor_SafeAnalysis();
}
public function enterNode(Twig_NodeInterface $node, Twig_Environment $env)
{
if ($node instanceof Twig_Node_Module) {
if ($env->hasExtension('escaper') && $defaultStrategy = $env->getExtension('escaper')->getDefaultStrategy($node->getAttribute('filename'))) {
$this->defaultStrategy = $defaultStrategy;
}
$this->safeVars = array();
} elseif ($node instanceof Twig_Node_AutoEscape) {
$this->statusStack[] = $node->getAttribute('value');
} elseif ($node instanceof Twig_Node_Block) {
$this->statusStack[] = isset($this->blocks[$node->getAttribute('name')]) ? $this->blocks[$node->getAttribute('name')] : $this->needEscaping($env);
} elseif ($node instanceof Twig_Node_Import) {
$this->safeVars[] = $node->getNode('var')->getAttribute('name');
}
return $node;
}
public function leaveNode(Twig_NodeInterface $node, Twig_Environment $env)
{
if ($node instanceof Twig_Node_Module) {
$this->defaultStrategy = false;
$this->safeVars = array();
} elseif ($node instanceof Twig_Node_Expression_Filter) {
return $this->preEscapeFilterNode($node, $env);
} elseif ($node instanceof Twig_Node_Print) {
return $this->escapePrintNode($node, $env, $this->needEscaping($env));
}
if ($node instanceof Twig_Node_AutoEscape || $node instanceof Twig_Node_Block) {
array_pop($this->statusStack);
} elseif ($node instanceof Twig_Node_BlockReference) {
$this->blocks[$node->getAttribute('name')] = $this->needEscaping($env);
}
return $node;
}
protected function escapePrintNode(Twig_Node_Print $node, Twig_Environment $env, $type)
{
if (false === $type) {
return $node;
}
$expression = $node->getNode('expr');
if ($this->isSafeFor($type, $expression, $env)) {
return $node;
}
$class = get_class($node);
return new $class(
$this->getEscaperFilter($type, $expression),
$node->getLine()
);
}
protected function preEscapeFilterNode(Twig_Node_Expression_Filter $filter, Twig_Environment $env)
{
$name = $filter->getNode('filter')->getAttribute('value');
$type = $env->getFilter($name)->getPreEscape();
if (null === $type) {
return $filter;
}
$node = $filter->getNode('node');
if ($this->isSafeFor($type, $node, $env)) {
return $filter;
}
$filter->setNode('node', $this->getEscaperFilter($type, $node));
return $filter;
}
protected function isSafeFor($type, Twig_NodeInterface $expression, $env)
{
$safe = $this->safeAnalysis->getSafe($expression);
if (null === $safe) {
if (null === $this->traverser) {
$this->traverser = new Twig_NodeTraverser($env, array($this->safeAnalysis));
}
$this->safeAnalysis->setSafeVars($this->safeVars);
$this->traverser->traverse($expression);
$safe = $this->safeAnalysis->getSafe($expression);
}
return in_array($type, $safe) || in_array('all', $safe);
}
protected function needEscaping(Twig_Environment $env)
{
if (count($this->statusStack)) {
return $this->statusStack[count($this->statusStack) - 1];
}
return $this->defaultStrategy ? $this->defaultStrategy : false;
}
protected function getEscaperFilter($type, Twig_NodeInterface $node)
{
$line = $node->getLine();
$name = new Twig_Node_Expression_Constant('escape', $line);
$args = new Twig_Node(array(new Twig_Node_Expression_Constant((string) $type, $line), new Twig_Node_Expression_Constant(null, $line), new Twig_Node_Expression_Constant(true, $line)));
return new Twig_Node_Expression_Filter($node, $name, $args, $line);
}
public function getPriority()
{
return 0;
}
}
}
namespace
{
class Twig_NodeVisitor_Optimizer implements Twig_NodeVisitorInterface
{
const OPTIMIZE_ALL = -1;
const OPTIMIZE_NONE = 0;
const OPTIMIZE_FOR = 2;
const OPTIMIZE_RAW_FILTER = 4;
const OPTIMIZE_VAR_ACCESS = 8;
protected $loops = array();
protected $optimizers;
protected $prependedNodes = array();
protected $inABody = false;
public function __construct($optimizers = -1)
{
if (!is_int($optimizers) || $optimizers > 2) {
throw new InvalidArgumentException(sprintf('Optimizer mode "%s" is not valid.', $optimizers));
}
$this->optimizers = $optimizers;
}
public function enterNode(Twig_NodeInterface $node, Twig_Environment $env)
{
if (self::OPTIMIZE_FOR === (self::OPTIMIZE_FOR & $this->optimizers)) {
$this->enterOptimizeFor($node, $env);
}
if (!version_compare(phpversion(),'5.4.0RC1','>=') && self::OPTIMIZE_VAR_ACCESS === (self::OPTIMIZE_VAR_ACCESS & $this->optimizers) && !$env->isStrictVariables() && !$env->hasExtension('sandbox')) {
if ($this->inABody) {
if (!$node instanceof Twig_Node_Expression) {
if (get_class($node) !=='Twig_Node') {
array_unshift($this->prependedNodes, array());
}
} else {
$node = $this->optimizeVariables($node, $env);
}
} elseif ($node instanceof Twig_Node_Body) {
$this->inABody = true;
}
}
return $node;
}
public function leaveNode(Twig_NodeInterface $node, Twig_Environment $env)
{
$expression = $node instanceof Twig_Node_Expression;
if (self::OPTIMIZE_FOR === (self::OPTIMIZE_FOR & $this->optimizers)) {
$this->leaveOptimizeFor($node, $env);
}
if (self::OPTIMIZE_RAW_FILTER === (self::OPTIMIZE_RAW_FILTER & $this->optimizers)) {
$node = $this->optimizeRawFilter($node, $env);
}
$node = $this->optimizePrintNode($node, $env);
if (self::OPTIMIZE_VAR_ACCESS === (self::OPTIMIZE_VAR_ACCESS & $this->optimizers) && !$env->isStrictVariables() && !$env->hasExtension('sandbox')) {
if ($node instanceof Twig_Node_Body) {
$this->inABody = false;
} elseif ($this->inABody) {
if (!$expression && get_class($node) !=='Twig_Node'&& $prependedNodes = array_shift($this->prependedNodes)) {
$nodes = array();
foreach (array_unique($prependedNodes) as $name) {
$nodes[] = new Twig_Node_SetTemp($name, $node->getLine());
}
$nodes[] = $node;
$node = new Twig_Node($nodes);
}
}
}
return $node;
}
protected function optimizeVariables($node, $env)
{
if ('Twig_Node_Expression_Name'=== get_class($node) && $node->isSimple()) {
$this->prependedNodes[0][] = $node->getAttribute('name');
return new Twig_Node_Expression_TempName($node->getAttribute('name'), $node->getLine());
}
return $node;
}
protected function optimizePrintNode($node, $env)
{
if (!$node instanceof Twig_Node_Print) {
return $node;
}
if (
$node->getNode('expr') instanceof Twig_Node_Expression_BlockReference ||
$node->getNode('expr') instanceof Twig_Node_Expression_Parent
) {
$node->getNode('expr')->setAttribute('output', true);
return $node->getNode('expr');
}
return $node;
}
protected function optimizeRawFilter($node, $env)
{
if ($node instanceof Twig_Node_Expression_Filter &&'raw'== $node->getNode('filter')->getAttribute('value')) {
return $node->getNode('node');
}
return $node;
}
protected function enterOptimizeFor($node, $env)
{
if ($node instanceof Twig_Node_For) {
$node->setAttribute('with_loop', false);
array_unshift($this->loops, $node);
} elseif (!$this->loops) {
return;
}
elseif ($node instanceof Twig_Node_Expression_Name &&'loop'=== $node->getAttribute('name')) {
$this->addLoopToCurrent();
}
elseif ($node instanceof Twig_Node_BlockReference || $node instanceof Twig_Node_Expression_BlockReference) {
$this->addLoopToCurrent();
}
elseif ($node instanceof Twig_Node_Include && !$node->getAttribute('only')) {
$this->addLoopToAll();
}
elseif ($node instanceof Twig_Node_Expression_GetAttr
&& (!$node->getNode('attribute') instanceof Twig_Node_Expression_Constant
||'parent'=== $node->getNode('attribute')->getAttribute('value')
)
&& (true === $this->loops[0]->getAttribute('with_loop')
|| ($node->getNode('node') instanceof Twig_Node_Expression_Name
&&'loop'=== $node->getNode('node')->getAttribute('name')
)
)
) {
$this->addLoopToAll();
}
}
protected function leaveOptimizeFor($node, $env)
{
if ($node instanceof Twig_Node_For) {
array_shift($this->loops);
}
}
protected function addLoopToCurrent()
{
$this->loops[0]->setAttribute('with_loop', true);
}
protected function addLoopToAll()
{
foreach ($this->loops as $loop) {
$loop->setAttribute('with_loop', true);
}
}
public function getPriority()
{
return 255;
}
}
}
namespace
{
class Twig_NodeVisitor_SafeAnalysis implements Twig_NodeVisitorInterface
{
protected $data = array();
protected $safeVars = array();
public function setSafeVars($safeVars)
{
$this->safeVars = $safeVars;
}
public function getSafe(Twig_NodeInterface $node)
{
$hash = spl_object_hash($node);
if (isset($this->data[$hash])) {
foreach ($this->data[$hash] as $bucket) {
if ($bucket['key'] === $node) {
return $bucket['value'];
}
}
}
}
protected function setSafe(Twig_NodeInterface $node, array $safe)
{
$hash = spl_object_hash($node);
if (isset($this->data[$hash])) {
foreach ($this->data[$hash] as &$bucket) {
if ($bucket['key'] === $node) {
$bucket['value'] = $safe;
return;
}
}
}
$this->data[$hash][] = array('key'=> $node,'value'=> $safe,
);
}
public function enterNode(Twig_NodeInterface $node, Twig_Environment $env)
{
return $node;
}
public function leaveNode(Twig_NodeInterface $node, Twig_Environment $env)
{
if ($node instanceof Twig_Node_Expression_Constant) {
$this->setSafe($node, array('all'));
} elseif ($node instanceof Twig_Node_Expression_BlockReference) {
$this->setSafe($node, array('all'));
} elseif ($node instanceof Twig_Node_Expression_Parent) {
$this->setSafe($node, array('all'));
} elseif ($node instanceof Twig_Node_Expression_Conditional) {
$safe = $this->intersectSafe($this->getSafe($node->getNode('expr2')), $this->getSafe($node->getNode('expr3')));
$this->setSafe($node, $safe);
} elseif ($node instanceof Twig_Node_Expression_Filter) {
$name = $node->getNode('filter')->getAttribute('value');
$args = $node->getNode('arguments');
if (false !== $filter = $env->getFilter($name)) {
$safe = $filter->getSafe($args);
if (null === $safe) {
$safe = $this->intersectSafe($this->getSafe($node->getNode('node')), $filter->getPreservesSafety());
}
$this->setSafe($node, $safe);
} else {
$this->setSafe($node, array());
}
} elseif ($node instanceof Twig_Node_Expression_Function) {
$name = $node->getAttribute('name');
$args = $node->getNode('arguments');
$function = $env->getFunction($name);
if (false !== $function) {
$this->setSafe($node, $function->getSafe($args));
} else {
$this->setSafe($node, array());
}
} elseif ($node instanceof Twig_Node_Expression_MethodCall) {
if ($node->getAttribute('safe')) {
$this->setSafe($node, array('all'));
} else {
$this->setSafe($node, array());
}
} elseif ($node instanceof Twig_Node_Expression_GetAttr && $node->getNode('node') instanceof Twig_Node_Expression_Name) {
$name = $node->getNode('node')->getAttribute('name');
if ('_self'== $name || in_array($name, $this->safeVars)) {
$this->setSafe($node, array('all'));
} else {
$this->setSafe($node, array());
}
} else {
$this->setSafe($node, array());
}
return $node;
}
protected function intersectSafe(array $a = null, array $b = null)
{
if (null === $a || null === $b) {
return array();
}
if (in_array('all', $a)) {
return $b;
}
if (in_array('all', $b)) {
return $a;
}
return array_intersect($a, $b);
}
public function getPriority()
{
return 0;
}
}
}
namespace
{
class Twig_NodeVisitor_Sandbox implements Twig_NodeVisitorInterface
{
protected $inAModule = false;
protected $tags;
protected $filters;
protected $functions;
public function enterNode(Twig_NodeInterface $node, Twig_Environment $env)
{
if ($node instanceof Twig_Node_Module) {
$this->inAModule = true;
$this->tags = array();
$this->filters = array();
$this->functions = array();
return $node;
} elseif ($this->inAModule) {
if ($node->getNodeTag()) {
$this->tags[] = $node->getNodeTag();
}
if ($node instanceof Twig_Node_Expression_Filter) {
$this->filters[] = $node->getNode('filter')->getAttribute('value');
}
if ($node instanceof Twig_Node_Expression_Function) {
$this->functions[] = $node->getAttribute('name');
}
if ($node instanceof Twig_Node_Print) {
return new Twig_Node_SandboxedPrint($node->getNode('expr'), $node->getLine(), $node->getNodeTag());
}
}
return $node;
}
public function leaveNode(Twig_NodeInterface $node, Twig_Environment $env)
{
if ($node instanceof Twig_Node_Module) {
$this->inAModule = false;
return new Twig_Node_SandboxedModule($node, array_unique($this->filters), array_unique($this->tags), array_unique($this->functions));
}
return $node;
}
public function getPriority()
{
return 0;
}
}
}
namespace
{
interface Twig_ParserInterface
{
public function parse(Twig_TokenStream $stream);
}
}
namespace
{
class Twig_Parser implements Twig_ParserInterface
{
protected $stack = array();
protected $stream;
protected $parent;
protected $handlers;
protected $visitors;
protected $expressionParser;
protected $blocks;
protected $blockStack;
protected $macros;
protected $env;
protected $reservedMacroNames;
protected $importedSymbols;
protected $traits;
protected $embeddedTemplates = array();
public function __construct(Twig_Environment $env)
{
$this->env = $env;
}
public function getEnvironment()
{
return $this->env;
}
public function getVarName()
{
return sprintf('__internal_%s', hash('sha1', uniqid(mt_rand(), true), false));
}
public function getFilename()
{
return $this->stream->getFilename();
}
public function parse(Twig_TokenStream $stream, $test = null, $dropNeedle = false)
{
$vars = get_object_vars($this);
unset($vars['stack'], $vars['env'], $vars['handlers'], $vars['visitors'], $vars['expressionParser']);
$this->stack[] = $vars;
if (null === $this->handlers) {
$this->handlers = $this->env->getTokenParsers();
$this->handlers->setParser($this);
}
if (null === $this->visitors) {
$this->visitors = $this->env->getNodeVisitors();
}
if (null === $this->expressionParser) {
$this->expressionParser = new Twig_ExpressionParser($this, $this->env->getUnaryOperators(), $this->env->getBinaryOperators());
}
$this->stream = $stream;
$this->parent = null;
$this->blocks = array();
$this->macros = array();
$this->traits = array();
$this->blockStack = array();
$this->importedSymbols = array(array());
$this->embeddedTemplates = array();
try {
$body = $this->subparse($test, $dropNeedle);
if (null !== $this->parent) {
if (null === $body = $this->filterBodyNodes($body)) {
$body = new Twig_Node();
}
}
} catch (Twig_Error_Syntax $e) {
if (!$e->getTemplateFile()) {
$e->setTemplateFile($this->getFilename());
}
if (!$e->getTemplateLine()) {
$e->setTemplateLine($this->stream->getCurrent()->getLine());
}
throw $e;
}
$node = new Twig_Node_Module(new Twig_Node_Body(array($body)), $this->parent, new Twig_Node($this->blocks), new Twig_Node($this->macros), new Twig_Node($this->traits), $this->embeddedTemplates, $this->getFilename());
$traverser = new Twig_NodeTraverser($this->env, $this->visitors);
$node = $traverser->traverse($node);
foreach (array_pop($this->stack) as $key => $val) {
$this->$key = $val;
}
return $node;
}
public function subparse($test, $dropNeedle = false)
{
$lineno = $this->getCurrentToken()->getLine();
$rv = array();
while (!$this->stream->isEOF()) {
switch ($this->getCurrentToken()->getType()) {
case Twig_Token::TEXT_TYPE:
$token = $this->stream->next();
$rv[] = new Twig_Node_Text($token->getValue(), $token->getLine());
break;
case Twig_Token::VAR_START_TYPE:
$token = $this->stream->next();
$expr = $this->expressionParser->parseExpression();
$this->stream->expect(Twig_Token::VAR_END_TYPE);
$rv[] = new Twig_Node_Print($expr, $token->getLine());
break;
case Twig_Token::BLOCK_START_TYPE:
$this->stream->next();
$token = $this->getCurrentToken();
if ($token->getType() !== Twig_Token::NAME_TYPE) {
throw new Twig_Error_Syntax('A block must start with a tag name', $token->getLine(), $this->getFilename());
}
if (null !== $test && call_user_func($test, $token)) {
if ($dropNeedle) {
$this->stream->next();
}
if (1 === count($rv)) {
return $rv[0];
}
return new Twig_Node($rv, array(), $lineno);
}
$subparser = $this->handlers->getTokenParser($token->getValue());
if (null === $subparser) {
if (null !== $test) {
$error = sprintf('Unexpected tag name "%s"', $token->getValue());
if (is_array($test) && isset($test[0]) && $test[0] instanceof Twig_TokenParserInterface) {
$error .= sprintf(' (expecting closing tag for the "%s" tag defined near line %s)', $test[0]->getTag(), $lineno);
}
throw new Twig_Error_Syntax($error, $token->getLine(), $this->getFilename());
}
$message = sprintf('Unknown tag name "%s"', $token->getValue());
if ($alternatives = $this->env->computeAlternatives($token->getValue(), array_keys($this->env->getTags()))) {
$message = sprintf('%s. Did you mean "%s"', $message, implode('", "', $alternatives));
}
throw new Twig_Error_Syntax($message, $token->getLine(), $this->getFilename());
}
$this->stream->next();
$node = $subparser->parse($token);
if (null !== $node) {
$rv[] = $node;
}
break;
default:
throw new Twig_Error_Syntax('Lexer or parser ended up in unsupported state.', 0, $this->getFilename());
}
}
if (1 === count($rv)) {
return $rv[0];
}
return new Twig_Node($rv, array(), $lineno);
}
public function addHandler($name, $class)
{
$this->handlers[$name] = $class;
}
public function addNodeVisitor(Twig_NodeVisitorInterface $visitor)
{
$this->visitors[] = $visitor;
}
public function getBlockStack()
{
return $this->blockStack;
}
public function peekBlockStack()
{
return $this->blockStack[count($this->blockStack) - 1];
}
public function popBlockStack()
{
array_pop($this->blockStack);
}
public function pushBlockStack($name)
{
$this->blockStack[] = $name;
}
public function hasBlock($name)
{
return isset($this->blocks[$name]);
}
public function getBlock($name)
{
return $this->blocks[$name];
}
public function setBlock($name, $value)
{
$this->blocks[$name] = new Twig_Node_Body(array($value), array(), $value->getLine());
}
public function hasMacro($name)
{
return isset($this->macros[$name]);
}
public function setMacro($name, Twig_Node_Macro $node)
{
if (null === $this->reservedMacroNames) {
$this->reservedMacroNames = array();
$r = new ReflectionClass($this->env->getBaseTemplateClass());
foreach ($r->getMethods() as $method) {
$this->reservedMacroNames[] = $method->getName();
}
}
if (in_array($name, $this->reservedMacroNames)) {
throw new Twig_Error_Syntax(sprintf('"%s" cannot be used as a macro name as it is a reserved keyword', $name), $node->getLine(), $this->getFilename());
}
$this->macros[$name] = $node;
}
public function addTrait($trait)
{
$this->traits[] = $trait;
}
public function hasTraits()
{
return count($this->traits) > 0;
}
public function embedTemplate(Twig_Node_Module $template)
{
$template->setIndex(mt_rand());
$this->embeddedTemplates[] = $template;
}
public function addImportedSymbol($type, $alias, $name = null, Twig_Node_Expression $node = null)
{
$this->importedSymbols[0][$type][$alias] = array('name'=> $name,'node'=> $node);
}
public function getImportedSymbol($type, $alias)
{
foreach ($this->importedSymbols as $functions) {
if (isset($functions[$type][$alias])) {
return $functions[$type][$alias];
}
}
}
public function isMainScope()
{
return 1 === count($this->importedSymbols);
}
public function pushLocalScope()
{
array_unshift($this->importedSymbols, array());
}
public function popLocalScope()
{
array_shift($this->importedSymbols);
}
public function getExpressionParser()
{
return $this->expressionParser;
}
public function getParent()
{
return $this->parent;
}
public function setParent($parent)
{
$this->parent = $parent;
}
public function getStream()
{
return $this->stream;
}
public function getCurrentToken()
{
return $this->stream->getCurrent();
}
protected function filterBodyNodes(Twig_NodeInterface $node)
{
if (
($node instanceof Twig_Node_Text && !ctype_space($node->getAttribute('data')))
||
(!$node instanceof Twig_Node_Text && !$node instanceof Twig_Node_BlockReference && $node instanceof Twig_NodeOutputInterface)
) {
if (false !== strpos((string) $node, chr(0xEF).chr(0xBB).chr(0xBF))) {
throw new Twig_Error_Syntax('A template that extends another one cannot have a body but a byte order mark (BOM) has been detected; it must be removed.', $node->getLine(), $this->getFilename());
}
throw new Twig_Error_Syntax('A template that extends another one cannot have a body.', $node->getLine(), $this->getFilename());
}
if ($node instanceof Twig_Node_Set) {
return $node;
}
if ($node instanceof Twig_NodeOutputInterface) {
return;
}
foreach ($node as $k => $n) {
if (null !== $n && null === $n = $this->filterBodyNodes($n)) {
$node->removeNode($k);
}
}
return $node;
}
}
}
namespace
{
class Twig_Sandbox_SecurityError extends Twig_Error
{
}
}
namespace
{
interface Twig_Sandbox_SecurityPolicyInterface
{
public function checkSecurity($tags, $filters, $functions);
public function checkMethodAllowed($obj, $method);
public function checkPropertyAllowed($obj, $method);
}
}
namespace
{
class Twig_Sandbox_SecurityPolicy implements Twig_Sandbox_SecurityPolicyInterface
{
protected $allowedTags;
protected $allowedFilters;
protected $allowedMethods;
protected $allowedProperties;
protected $allowedFunctions;
public function __construct(array $allowedTags = array(), array $allowedFilters = array(), array $allowedMethods = array(), array $allowedProperties = array(), array $allowedFunctions = array())
{
$this->allowedTags = $allowedTags;
$this->allowedFilters = $allowedFilters;
$this->setAllowedMethods($allowedMethods);
$this->allowedProperties = $allowedProperties;
$this->allowedFunctions = $allowedFunctions;
}
public function setAllowedTags(array $tags)
{
$this->allowedTags = $tags;
}
public function setAllowedFilters(array $filters)
{
$this->allowedFilters = $filters;
}
public function setAllowedMethods(array $methods)
{
$this->allowedMethods = array();
foreach ($methods as $class => $m) {
$this->allowedMethods[$class] = array_map('strtolower', is_array($m) ? $m : array($m));
}
}
public function setAllowedProperties(array $properties)
{
$this->allowedProperties = $properties;
}
public function setAllowedFunctions(array $functions)
{
$this->allowedFunctions = $functions;
}
public function checkSecurity($tags, $filters, $functions)
{
foreach ($tags as $tag) {
if (!in_array($tag, $this->allowedTags)) {
throw new Twig_Sandbox_SecurityError(sprintf('Tag "%s" is not allowed.', $tag));
}
}
foreach ($filters as $filter) {
if (!in_array($filter, $this->allowedFilters)) {
throw new Twig_Sandbox_SecurityError(sprintf('Filter "%s" is not allowed.', $filter));
}
}
foreach ($functions as $function) {
if (!in_array($function, $this->allowedFunctions)) {
throw new Twig_Sandbox_SecurityError(sprintf('Function "%s" is not allowed.', $function));
}
}
}
public function checkMethodAllowed($obj, $method)
{
if ($obj instanceof Twig_TemplateInterface || $obj instanceof Twig_Markup) {
return true;
}
$allowed = false;
$method = strtolower($method);
foreach ($this->allowedMethods as $class => $methods) {
if ($obj instanceof $class) {
$allowed = in_array($method, $methods);
break;
}
}
if (!$allowed) {
throw new Twig_Sandbox_SecurityError(sprintf('Calling "%s" method on a "%s" object is not allowed.', $method, get_class($obj)));
}
}
public function checkPropertyAllowed($obj, $property)
{
$allowed = false;
foreach ($this->allowedProperties as $class => $properties) {
if ($obj instanceof $class) {
$allowed = in_array($property, is_array($properties) ? $properties : array($properties));
break;
}
}
if (!$allowed) {
throw new Twig_Sandbox_SecurityError(sprintf('Calling "%s" property on a "%s" object is not allowed.', $property, get_class($obj)));
}
}
}
}
namespace
{
interface Twig_TemplateInterface
{
const ANY_CALL ='any';
const ARRAY_CALL ='array';
const METHOD_CALL ='method';
public function render(array $context);
public function display(array $context, array $blocks = array());
public function getEnvironment();
}
}
namespace
{
abstract class Twig_Template implements Twig_TemplateInterface
{
protected static $cache = array();
protected $parent;
protected $parents;
protected $env;
protected $blocks;
protected $traits;
public function __construct(Twig_Environment $env)
{
$this->env = $env;
$this->blocks = array();
$this->traits = array();
}
abstract public function getTemplateName();
public function getEnvironment()
{
return $this->env;
}
public function getParent(array $context)
{
if (null !== $this->parent) {
return $this->parent;
}
$parent = $this->doGetParent($context);
if (false === $parent) {
return false;
} elseif ($parent instanceof Twig_Template) {
$name = $parent->getTemplateName();
$this->parents[$name] = $parent;
$parent = $name;
} elseif (!isset($this->parents[$parent])) {
$this->parents[$parent] = $this->env->loadTemplate($parent);
}
return $this->parents[$parent];
}
protected function doGetParent(array $context)
{
return false;
}
public function isTraitable()
{
return true;
}
public function displayParentBlock($name, array $context, array $blocks = array())
{
$name = (string) $name;
if (isset($this->traits[$name])) {
$this->traits[$name][0]->displayBlock($name, $context, $blocks);
} elseif (false !== $parent = $this->getParent($context)) {
$parent->displayBlock($name, $context, $blocks);
} else {
throw new Twig_Error_Runtime(sprintf('The template has no parent and no traits defining the "%s" block', $name), -1, $this->getTemplateName());
}
}
public function displayBlock($name, array $context, array $blocks = array())
{
$name = (string) $name;
if (isset($blocks[$name])) {
$b = $blocks;
unset($b[$name]);
call_user_func($blocks[$name], $context, $b);
} elseif (isset($this->blocks[$name])) {
call_user_func($this->blocks[$name], $context, $blocks);
} elseif (false !== $parent = $this->getParent($context)) {
$parent->displayBlock($name, $context, array_merge($this->blocks, $blocks));
}
}
public function renderParentBlock($name, array $context, array $blocks = array())
{
ob_start();
$this->displayParentBlock($name, $context, $blocks);
return ob_get_clean();
}
public function renderBlock($name, array $context, array $blocks = array())
{
ob_start();
$this->displayBlock($name, $context, $blocks);
return ob_get_clean();
}
public function hasBlock($name)
{
return isset($this->blocks[(string) $name]);
}
public function getBlockNames()
{
return array_keys($this->blocks);
}
public function getBlocks()
{
return $this->blocks;
}
public function display(array $context, array $blocks = array())
{
$this->displayWithErrorHandling($this->env->mergeGlobals($context), $blocks);
}
public function render(array $context)
{
$level = ob_get_level();
ob_start();
try {
$this->display($context);
} catch (Exception $e) {
while (ob_get_level() > $level) {
ob_end_clean();
}
throw $e;
}
return ob_get_clean();
}
protected function displayWithErrorHandling(array $context, array $blocks = array())
{
try {
$this->doDisplay($context, $blocks);
} catch (Twig_Error $e) {
if (!$e->getTemplateFile()) {
$e->setTemplateFile($this->getTemplateName());
}
if (false === $e->getTemplateLine()) {
$e->setTemplateLine(-1);
$e->guess();
}
throw $e;
} catch (Exception $e) {
throw new Twig_Error_Runtime(sprintf('An exception has been thrown during the rendering of a template ("%s").', $e->getMessage()), -1, null, $e);
}
}
abstract protected function doDisplay(array $context, array $blocks = array());
final protected function getContext($context, $item, $ignoreStrictCheck = false)
{
if (!array_key_exists($item, $context)) {
if ($ignoreStrictCheck || !$this->env->isStrictVariables()) {
return null;
}
throw new Twig_Error_Runtime(sprintf('Variable "%s" does not exist', $item), -1, $this->getTemplateName());
}
return $context[$item];
}
protected function getAttribute($object, $item, array $arguments = array(), $type = Twig_TemplateInterface::ANY_CALL, $isDefinedTest = false, $ignoreStrictCheck = false)
{
if (Twig_TemplateInterface::METHOD_CALL !== $type) {
$arrayItem = is_bool($item) || is_float($item) ? (int) $item : $item;
if ((is_array($object) && array_key_exists($arrayItem, $object))
|| ($object instanceof ArrayAccess && isset($object[$arrayItem]))
) {
if ($isDefinedTest) {
return true;
}
return $object[$arrayItem];
}
if (Twig_TemplateInterface::ARRAY_CALL === $type || !is_object($object)) {
if ($isDefinedTest) {
return false;
}
if ($ignoreStrictCheck || !$this->env->isStrictVariables()) {
return null;
}
if (is_object($object)) {
throw new Twig_Error_Runtime(sprintf('Key "%s" in object (with ArrayAccess) of type "%s" does not exist', $arrayItem, get_class($object)), -1, $this->getTemplateName());
} elseif (is_array($object)) {
throw new Twig_Error_Runtime(sprintf('Key "%s" for array with keys "%s" does not exist', $arrayItem, implode(', ', array_keys($object))), -1, $this->getTemplateName());
} elseif (Twig_TemplateInterface::ARRAY_CALL === $type) {
throw new Twig_Error_Runtime(sprintf('Impossible to access a key ("%s") on a %s variable ("%s")', $item, gettype($object), $object), -1, $this->getTemplateName());
} else {
throw new Twig_Error_Runtime(sprintf('Impossible to access an attribute ("%s") on a %s variable ("%s")', $item, gettype($object), $object), -1, $this->getTemplateName());
}
}
}
if (!is_object($object)) {
if ($isDefinedTest) {
return false;
}
if ($ignoreStrictCheck || !$this->env->isStrictVariables()) {
return null;
}
throw new Twig_Error_Runtime(sprintf('Impossible to invoke a method ("%s") on a %s variable ("%s")', $item, gettype($object), $object), -1, $this->getTemplateName());
}
$class = get_class($object);
if (Twig_TemplateInterface::METHOD_CALL !== $type) {
if (isset($object->$item) || array_key_exists((string) $item, $object)) {
if ($isDefinedTest) {
return true;
}
if ($this->env->hasExtension('sandbox')) {
$this->env->getExtension('sandbox')->checkPropertyAllowed($object, $item);
}
return $object->$item;
}
}
if (!isset(self::$cache[$class]['methods'])) {
self::$cache[$class]['methods'] = array_change_key_case(array_flip(get_class_methods($object)));
}
$lcItem = strtolower($item);
if (isset(self::$cache[$class]['methods'][$lcItem])) {
$method = (string) $item;
} elseif (isset(self::$cache[$class]['methods']['get'.$lcItem])) {
$method ='get'.$item;
} elseif (isset(self::$cache[$class]['methods']['is'.$lcItem])) {
$method ='is'.$item;
} elseif (isset(self::$cache[$class]['methods']['__call'])) {
$method = (string) $item;
} else {
if ($isDefinedTest) {
return false;
}
if ($ignoreStrictCheck || !$this->env->isStrictVariables()) {
return null;
}
throw new Twig_Error_Runtime(sprintf('Method "%s" for object "%s" does not exist', $item, get_class($object)), -1, $this->getTemplateName());
}
if ($isDefinedTest) {
return true;
}
if ($this->env->hasExtension('sandbox')) {
$this->env->getExtension('sandbox')->checkMethodAllowed($object, $method);
}
$ret = call_user_func_array(array($object, $method), $arguments);
if ($object instanceof Twig_TemplateInterface) {
return $ret ===''?'': new Twig_Markup($ret, $this->env->getCharset());
}
return $ret;
}
public static function clearCache()
{
self::$cache = array();
}
}
}
namespace
{
class Twig_Token
{
protected $value;
protected $type;
protected $lineno;
const EOF_TYPE = -1;
const TEXT_TYPE = 0;
const BLOCK_START_TYPE = 1;
const VAR_START_TYPE = 2;
const BLOCK_END_TYPE = 3;
const VAR_END_TYPE = 4;
const NAME_TYPE = 5;
const NUMBER_TYPE = 6;
const STRING_TYPE = 7;
const OPERATOR_TYPE = 8;
const PUNCTUATION_TYPE = 9;
const INTERPOLATION_START_TYPE = 10;
const INTERPOLATION_END_TYPE = 11;
public function __construct($type, $value, $lineno)
{
$this->type = $type;
$this->value = $value;
$this->lineno = $lineno;
}
public function __toString()
{
return sprintf('%s(%s)', self::typeToString($this->type, true, $this->lineno), $this->value);
}
public function test($type, $values = null)
{
if (null === $values && !is_int($type)) {
$values = $type;
$type = self::NAME_TYPE;
}
return ($this->type === $type) && (
null === $values ||
(is_array($values) && in_array($this->value, $values)) ||
$this->value == $values
);
}
public function getLine()
{
return $this->lineno;
}
public function getType()
{
return $this->type;
}
public function getValue()
{
return $this->value;
}
public static function typeToString($type, $short = false, $line = -1)
{
switch ($type) {
case self::EOF_TYPE:
$name ='EOF_TYPE';
break;
case self::TEXT_TYPE:
$name ='TEXT_TYPE';
break;
case self::BLOCK_START_TYPE:
$name ='BLOCK_START_TYPE';
break;
case self::VAR_START_TYPE:
$name ='VAR_START_TYPE';
break;
case self::BLOCK_END_TYPE:
$name ='BLOCK_END_TYPE';
break;
case self::VAR_END_TYPE:
$name ='VAR_END_TYPE';
break;
case self::NAME_TYPE:
$name ='NAME_TYPE';
break;
case self::NUMBER_TYPE:
$name ='NUMBER_TYPE';
break;
case self::STRING_TYPE:
$name ='STRING_TYPE';
break;
case self::OPERATOR_TYPE:
$name ='OPERATOR_TYPE';
break;
case self::PUNCTUATION_TYPE:
$name ='PUNCTUATION_TYPE';
break;
case self::INTERPOLATION_START_TYPE:
$name ='INTERPOLATION_START_TYPE';
break;
case self::INTERPOLATION_END_TYPE:
$name ='INTERPOLATION_END_TYPE';
break;
default:
throw new LogicException(sprintf('Token of type "%s" does not exist.', $type));
}
return $short ? $name :'Twig_Token::'.$name;
}
public static function typeToEnglish($type, $line = -1)
{
switch ($type) {
case self::EOF_TYPE:
return'end of template';
case self::TEXT_TYPE:
return'text';
case self::BLOCK_START_TYPE:
return'begin of statement block';
case self::VAR_START_TYPE:
return'begin of print statement';
case self::BLOCK_END_TYPE:
return'end of statement block';
case self::VAR_END_TYPE:
return'end of print statement';
case self::NAME_TYPE:
return'name';
case self::NUMBER_TYPE:
return'number';
case self::STRING_TYPE:
return'string';
case self::OPERATOR_TYPE:
return'operator';
case self::PUNCTUATION_TYPE:
return'punctuation';
case self::INTERPOLATION_START_TYPE:
return'begin of string interpolation';
case self::INTERPOLATION_END_TYPE:
return'end of string interpolation';
default:
throw new LogicException(sprintf('Token of type "%s" does not exist.', $type));
}
}
}
}
namespace
{
interface Twig_TokenParserInterface
{
public function setParser(Twig_Parser $parser);
public function parse(Twig_Token $token);
public function getTag();
}
}
namespace
{
abstract class Twig_TokenParser implements Twig_TokenParserInterface
{
protected $parser;
public function setParser(Twig_Parser $parser)
{
$this->parser = $parser;
}
}
}
namespace
{
class Twig_TokenParser_AutoEscape extends Twig_TokenParser
{
public function parse(Twig_Token $token)
{
$lineno = $token->getLine();
$stream = $this->parser->getStream();
if ($stream->test(Twig_Token::BLOCK_END_TYPE)) {
$value ='html';
} else {
$expr = $this->parser->getExpressionParser()->parseExpression();
if (!$expr instanceof Twig_Node_Expression_Constant) {
throw new Twig_Error_Syntax('An escaping strategy must be a string or a Boolean.', $stream->getCurrent()->getLine(), $stream->getFilename());
}
$value = $expr->getAttribute('value');
$compat = true === $value || false === $value;
if (true === $value) {
$value ='html';
}
if ($compat && $stream->test(Twig_Token::NAME_TYPE)) {
if (false === $value) {
throw new Twig_Error_Syntax('Unexpected escaping strategy as you set autoescaping to false.', $stream->getCurrent()->getLine(), $stream->getFilename());
}
$value = $stream->next()->getValue();
}
}
$stream->expect(Twig_Token::BLOCK_END_TYPE);
$body = $this->parser->subparse(array($this,'decideBlockEnd'), true);
$stream->expect(Twig_Token::BLOCK_END_TYPE);
return new Twig_Node_AutoEscape($value, $body, $lineno, $this->getTag());
}
public function decideBlockEnd(Twig_Token $token)
{
return $token->test('endautoescape');
}
public function getTag()
{
return'autoescape';
}
}
}
namespace
{
class Twig_TokenParser_Block extends Twig_TokenParser
{
public function parse(Twig_Token $token)
{
$lineno = $token->getLine();
$stream = $this->parser->getStream();
$name = $stream->expect(Twig_Token::NAME_TYPE)->getValue();
if ($this->parser->hasBlock($name)) {
throw new Twig_Error_Syntax(sprintf("The block '$name' has already been defined line %d", $this->parser->getBlock($name)->getLine()), $stream->getCurrent()->getLine(), $stream->getFilename());
}
$this->parser->setBlock($name, $block = new Twig_Node_Block($name, new Twig_Node(array()), $lineno));
$this->parser->pushLocalScope();
$this->parser->pushBlockStack($name);
if ($stream->test(Twig_Token::BLOCK_END_TYPE)) {
$stream->next();
$body = $this->parser->subparse(array($this,'decideBlockEnd'), true);
if ($stream->test(Twig_Token::NAME_TYPE)) {
$value = $stream->next()->getValue();
if ($value != $name) {
throw new Twig_Error_Syntax(sprintf("Expected endblock for block '$name' (but %s given)", $value), $stream->getCurrent()->getLine(), $stream->getFilename());
}
}
} else {
$body = new Twig_Node(array(
new Twig_Node_Print($this->parser->getExpressionParser()->parseExpression(), $lineno),
));
}
$stream->expect(Twig_Token::BLOCK_END_TYPE);
$block->setNode('body', $body);
$this->parser->popBlockStack();
$this->parser->popLocalScope();
return new Twig_Node_BlockReference($name, $lineno, $this->getTag());
}
public function decideBlockEnd(Twig_Token $token)
{
return $token->test('endblock');
}
public function getTag()
{
return'block';
}
}
}
namespace
{
class Twig_TokenParser_Do extends Twig_TokenParser
{
public function parse(Twig_Token $token)
{
$expr = $this->parser->getExpressionParser()->parseExpression();
$this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
return new Twig_Node_Do($expr, $token->getLine(), $this->getTag());
}
public function getTag()
{
return'do';
}
}
}
namespace
{
class Twig_TokenParser_Include extends Twig_TokenParser
{
public function parse(Twig_Token $token)
{
$expr = $this->parser->getExpressionParser()->parseExpression();
list($variables, $only, $ignoreMissing) = $this->parseArguments();
return new Twig_Node_Include($expr, $variables, $only, $ignoreMissing, $token->getLine(), $this->getTag());
}
protected function parseArguments()
{
$stream = $this->parser->getStream();
$ignoreMissing = false;
if ($stream->test(Twig_Token::NAME_TYPE,'ignore')) {
$stream->next();
$stream->expect(Twig_Token::NAME_TYPE,'missing');
$ignoreMissing = true;
}
$variables = null;
if ($stream->test(Twig_Token::NAME_TYPE,'with')) {
$stream->next();
$variables = $this->parser->getExpressionParser()->parseExpression();
}
$only = false;
if ($stream->test(Twig_Token::NAME_TYPE,'only')) {
$stream->next();
$only = true;
}
$stream->expect(Twig_Token::BLOCK_END_TYPE);
return array($variables, $only, $ignoreMissing);
}
public function getTag()
{
return'include';
}
}
}
namespace
{
class Twig_TokenParser_Embed extends Twig_TokenParser_Include
{
public function parse(Twig_Token $token)
{
$stream = $this->parser->getStream();
$parent = $this->parser->getExpressionParser()->parseExpression();
list($variables, $only, $ignoreMissing) = $this->parseArguments();
$stream->injectTokens(array(
new Twig_Token(Twig_Token::BLOCK_START_TYPE,'', $token->getLine()),
new Twig_Token(Twig_Token::NAME_TYPE,'extends', $token->getLine()),
new Twig_Token(Twig_Token::STRING_TYPE,'__parent__', $token->getLine()),
new Twig_Token(Twig_Token::BLOCK_END_TYPE,'', $token->getLine()),
));
$module = $this->parser->parse($stream, array($this,'decideBlockEnd'), true);
$module->setNode('parent', $parent);
$this->parser->embedTemplate($module);
$stream->expect(Twig_Token::BLOCK_END_TYPE);
return new Twig_Node_Embed($module->getAttribute('filename'), $module->getAttribute('index'), $variables, $only, $ignoreMissing, $token->getLine(), $this->getTag());
}
public function decideBlockEnd(Twig_Token $token)
{
return $token->test('endembed');
}
public function getTag()
{
return'embed';
}
}
}
namespace
{
class Twig_TokenParser_Extends extends Twig_TokenParser
{
public function parse(Twig_Token $token)
{
if (!$this->parser->isMainScope()) {
throw new Twig_Error_Syntax('Cannot extend from a block', $token->getLine(), $this->parser->getFilename());
}
if (null !== $this->parser->getParent()) {
throw new Twig_Error_Syntax('Multiple extends tags are forbidden', $token->getLine(), $this->parser->getFilename());
}
$this->parser->setParent($this->parser->getExpressionParser()->parseExpression());
$this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
}
public function getTag()
{
return'extends';
}
}
}
namespace
{
class Twig_TokenParser_Filter extends Twig_TokenParser
{
public function parse(Twig_Token $token)
{
$name = $this->parser->getVarName();
$ref = new Twig_Node_Expression_BlockReference(new Twig_Node_Expression_Constant($name, $token->getLine()), true, $token->getLine(), $this->getTag());
$filter = $this->parser->getExpressionParser()->parseFilterExpressionRaw($ref, $this->getTag());
$this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
$body = $this->parser->subparse(array($this,'decideBlockEnd'), true);
$this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
$block = new Twig_Node_Block($name, $body, $token->getLine());
$this->parser->setBlock($name, $block);
return new Twig_Node_Print($filter, $token->getLine(), $this->getTag());
}
public function decideBlockEnd(Twig_Token $token)
{
return $token->test('endfilter');
}
public function getTag()
{
return'filter';
}
}
}
namespace
{
class Twig_TokenParser_Flush extends Twig_TokenParser
{
public function parse(Twig_Token $token)
{
$this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
return new Twig_Node_Flush($token->getLine(), $this->getTag());
}
public function getTag()
{
return'flush';
}
}
}
namespace
{
class Twig_TokenParser_For extends Twig_TokenParser
{
public function parse(Twig_Token $token)
{
$lineno = $token->getLine();
$stream = $this->parser->getStream();
$targets = $this->parser->getExpressionParser()->parseAssignmentExpression();
$stream->expect(Twig_Token::OPERATOR_TYPE,'in');
$seq = $this->parser->getExpressionParser()->parseExpression();
$ifexpr = null;
if ($stream->test(Twig_Token::NAME_TYPE,'if')) {
$stream->next();
$ifexpr = $this->parser->getExpressionParser()->parseExpression();
}
$stream->expect(Twig_Token::BLOCK_END_TYPE);
$body = $this->parser->subparse(array($this,'decideForFork'));
if ($stream->next()->getValue() =='else') {
$stream->expect(Twig_Token::BLOCK_END_TYPE);
$else = $this->parser->subparse(array($this,'decideForEnd'), true);
} else {
$else = null;
}
$stream->expect(Twig_Token::BLOCK_END_TYPE);
if (count($targets) > 1) {
$keyTarget = $targets->getNode(0);
$keyTarget = new Twig_Node_Expression_AssignName($keyTarget->getAttribute('name'), $keyTarget->getLine());
$valueTarget = $targets->getNode(1);
$valueTarget = new Twig_Node_Expression_AssignName($valueTarget->getAttribute('name'), $valueTarget->getLine());
} else {
$keyTarget = new Twig_Node_Expression_AssignName('_key', $lineno);
$valueTarget = $targets->getNode(0);
$valueTarget = new Twig_Node_Expression_AssignName($valueTarget->getAttribute('name'), $valueTarget->getLine());
}
if ($ifexpr) {
$this->checkLoopUsageCondition($stream, $ifexpr);
$this->checkLoopUsageBody($stream, $body);
}
return new Twig_Node_For($keyTarget, $valueTarget, $seq, $ifexpr, $body, $else, $lineno, $this->getTag());
}
public function decideForFork(Twig_Token $token)
{
return $token->test(array('else','endfor'));
}
public function decideForEnd(Twig_Token $token)
{
return $token->test('endfor');
}
protected function checkLoopUsageCondition(Twig_TokenStream $stream, Twig_NodeInterface $node)
{
if ($node instanceof Twig_Node_Expression_GetAttr && $node->getNode('node') instanceof Twig_Node_Expression_Name &&'loop'== $node->getNode('node')->getAttribute('name')) {
throw new Twig_Error_Syntax('The "loop" variable cannot be used in a looping condition', $node->getLine(), $stream->getFilename());
}
foreach ($node as $n) {
if (!$n) {
continue;
}
$this->checkLoopUsageCondition($stream, $n);
}
}
protected function checkLoopUsageBody(Twig_TokenStream $stream, Twig_NodeInterface $node)
{
if ($node instanceof Twig_Node_Expression_GetAttr && $node->getNode('node') instanceof Twig_Node_Expression_Name &&'loop'== $node->getNode('node')->getAttribute('name')) {
$attribute = $node->getNode('attribute');
if ($attribute instanceof Twig_Node_Expression_Constant && in_array($attribute->getAttribute('value'), array('length','revindex0','revindex','last'))) {
throw new Twig_Error_Syntax(sprintf('The "loop.%s" variable is not defined when looping with a condition', $attribute->getAttribute('value')), $node->getLine(), $stream->getFilename());
}
}
if ($node instanceof Twig_Node_For) {
return;
}
foreach ($node as $n) {
if (!$n) {
continue;
}
$this->checkLoopUsageBody($stream, $n);
}
}
public function getTag()
{
return'for';
}
}
}
namespace
{
class Twig_TokenParser_From extends Twig_TokenParser
{
public function parse(Twig_Token $token)
{
$macro = $this->parser->getExpressionParser()->parseExpression();
$stream = $this->parser->getStream();
$stream->expect('import');
$targets = array();
do {
$name = $stream->expect(Twig_Token::NAME_TYPE)->getValue();
$alias = $name;
if ($stream->test('as')) {
$stream->next();
$alias = $stream->expect(Twig_Token::NAME_TYPE)->getValue();
}
$targets[$name] = $alias;
if (!$stream->test(Twig_Token::PUNCTUATION_TYPE,',')) {
break;
}
$stream->next();
} while (true);
$stream->expect(Twig_Token::BLOCK_END_TYPE);
$node = new Twig_Node_Import($macro, new Twig_Node_Expression_AssignName($this->parser->getVarName(), $token->getLine()), $token->getLine(), $this->getTag());
foreach ($targets as $name => $alias) {
$this->parser->addImportedSymbol('function', $alias,'get'.$name, $node->getNode('var'));
}
return $node;
}
public function getTag()
{
return'from';
}
}
}
namespace
{
class Twig_TokenParser_If extends Twig_TokenParser
{
public function parse(Twig_Token $token)
{
$lineno = $token->getLine();
$expr = $this->parser->getExpressionParser()->parseExpression();
$stream = $this->parser->getStream();
$stream->expect(Twig_Token::BLOCK_END_TYPE);
$body = $this->parser->subparse(array($this,'decideIfFork'));
$tests = array($expr, $body);
$else = null;
$end = false;
while (!$end) {
switch ($stream->next()->getValue()) {
case'else':
$stream->expect(Twig_Token::BLOCK_END_TYPE);
$else = $this->parser->subparse(array($this,'decideIfEnd'));
break;
case'elseif':
$expr = $this->parser->getExpressionParser()->parseExpression();
$stream->expect(Twig_Token::BLOCK_END_TYPE);
$body = $this->parser->subparse(array($this,'decideIfFork'));
$tests[] = $expr;
$tests[] = $body;
break;
case'endif':
$end = true;
break;
default:
throw new Twig_Error_Syntax(sprintf('Unexpected end of template. Twig was looking for the following tags "else", "elseif", or "endif" to close the "if" block started at line %d)', $lineno), $stream->getCurrent()->getLine(), $stream->getFilename());
}
}
$stream->expect(Twig_Token::BLOCK_END_TYPE);
return new Twig_Node_If(new Twig_Node($tests), $else, $lineno, $this->getTag());
}
public function decideIfFork(Twig_Token $token)
{
return $token->test(array('elseif','else','endif'));
}
public function decideIfEnd(Twig_Token $token)
{
return $token->test(array('endif'));
}
public function getTag()
{
return'if';
}
}
}
namespace
{
class Twig_TokenParser_Import extends Twig_TokenParser
{
public function parse(Twig_Token $token)
{
$macro = $this->parser->getExpressionParser()->parseExpression();
$this->parser->getStream()->expect('as');
$var = new Twig_Node_Expression_AssignName($this->parser->getStream()->expect(Twig_Token::NAME_TYPE)->getValue(), $token->getLine());
$this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
$this->parser->addImportedSymbol('template', $var->getAttribute('name'));
return new Twig_Node_Import($macro, $var, $token->getLine(), $this->getTag());
}
public function getTag()
{
return'import';
}
}
}
namespace
{
class Twig_TokenParser_Macro extends Twig_TokenParser
{
public function parse(Twig_Token $token)
{
$lineno = $token->getLine();
$stream = $this->parser->getStream();
$name = $stream->expect(Twig_Token::NAME_TYPE)->getValue();
$arguments = $this->parser->getExpressionParser()->parseArguments(true, true);
$stream->expect(Twig_Token::BLOCK_END_TYPE);
$this->parser->pushLocalScope();
$body = $this->parser->subparse(array($this,'decideBlockEnd'), true);
if ($stream->test(Twig_Token::NAME_TYPE)) {
$value = $stream->next()->getValue();
if ($value != $name) {
throw new Twig_Error_Syntax(sprintf("Expected endmacro for macro '$name' (but %s given)", $value), $stream->getCurrent()->getLine(), $stream->getFilename());
}
}
$this->parser->popLocalScope();
$stream->expect(Twig_Token::BLOCK_END_TYPE);
$this->parser->setMacro($name, new Twig_Node_Macro($name, new Twig_Node_Body(array($body)), $arguments, $lineno, $this->getTag()));
}
public function decideBlockEnd(Twig_Token $token)
{
return $token->test('endmacro');
}
public function getTag()
{
return'macro';
}
}
}
namespace
{
class Twig_TokenParser_Sandbox extends Twig_TokenParser
{
public function parse(Twig_Token $token)
{
$this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
$body = $this->parser->subparse(array($this,'decideBlockEnd'), true);
$this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
if (!$body instanceof Twig_Node_Include) {
foreach ($body as $node) {
if ($node instanceof Twig_Node_Text && ctype_space($node->getAttribute('data'))) {
continue;
}
if (!$node instanceof Twig_Node_Include) {
throw new Twig_Error_Syntax('Only "include" tags are allowed within a "sandbox" section', $node->getLine(), $this->parser->getFilename());
}
}
}
return new Twig_Node_Sandbox($body, $token->getLine(), $this->getTag());
}
public function decideBlockEnd(Twig_Token $token)
{
return $token->test('endsandbox');
}
public function getTag()
{
return'sandbox';
}
}
}
namespace
{
class Twig_TokenParser_Set extends Twig_TokenParser
{
public function parse(Twig_Token $token)
{
$lineno = $token->getLine();
$stream = $this->parser->getStream();
$names = $this->parser->getExpressionParser()->parseAssignmentExpression();
$capture = false;
if ($stream->test(Twig_Token::OPERATOR_TYPE,'=')) {
$stream->next();
$values = $this->parser->getExpressionParser()->parseMultitargetExpression();
$stream->expect(Twig_Token::BLOCK_END_TYPE);
if (count($names) !== count($values)) {
throw new Twig_Error_Syntax("When using set, you must have the same number of variables and assignments.", $stream->getCurrent()->getLine(), $stream->getFilename());
}
} else {
$capture = true;
if (count($names) > 1) {
throw new Twig_Error_Syntax("When using set with a block, you cannot have a multi-target.", $stream->getCurrent()->getLine(), $stream->getFilename());
}
$stream->expect(Twig_Token::BLOCK_END_TYPE);
$values = $this->parser->subparse(array($this,'decideBlockEnd'), true);
$stream->expect(Twig_Token::BLOCK_END_TYPE);
}
return new Twig_Node_Set($capture, $names, $values, $lineno, $this->getTag());
}
public function decideBlockEnd(Twig_Token $token)
{
return $token->test('endset');
}
public function getTag()
{
return'set';
}
}
}
namespace
{
class Twig_TokenParser_Spaceless extends Twig_TokenParser
{
public function parse(Twig_Token $token)
{
$lineno = $token->getLine();
$this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
$body = $this->parser->subparse(array($this,'decideSpacelessEnd'), true);
$this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
return new Twig_Node_Spaceless($body, $lineno, $this->getTag());
}
public function decideSpacelessEnd(Twig_Token $token)
{
return $token->test('endspaceless');
}
public function getTag()
{
return'spaceless';
}
}
}
namespace
{
class Twig_TokenParser_Use extends Twig_TokenParser
{
public function parse(Twig_Token $token)
{
$template = $this->parser->getExpressionParser()->parseExpression();
$stream = $this->parser->getStream();
if (!$template instanceof Twig_Node_Expression_Constant) {
throw new Twig_Error_Syntax('The template references in a "use" statement must be a string.', $stream->getCurrent()->getLine(), $stream->getFilename());
}
$targets = array();
if ($stream->test('with')) {
$stream->next();
do {
$name = $stream->expect(Twig_Token::NAME_TYPE)->getValue();
$alias = $name;
if ($stream->test('as')) {
$stream->next();
$alias = $stream->expect(Twig_Token::NAME_TYPE)->getValue();
}
$targets[$name] = new Twig_Node_Expression_Constant($alias, -1);
if (!$stream->test(Twig_Token::PUNCTUATION_TYPE,',')) {
break;
}
$stream->next();
} while (true);
}
$stream->expect(Twig_Token::BLOCK_END_TYPE);
$this->parser->addTrait(new Twig_Node(array('template'=> $template,'targets'=> new Twig_Node($targets))));
}
public function getTag()
{
return'use';
}
}
}
namespace
{
interface Twig_TokenParserBrokerInterface
{
public function getTokenParser($tag);
public function setParser(Twig_ParserInterface $parser);
public function getParser();
}
}
namespace
{
class Twig_TokenParserBroker implements Twig_TokenParserBrokerInterface
{
protected $parser;
protected $parsers = array();
protected $brokers = array();
public function __construct($parsers = array(), $brokers = array())
{
foreach ($parsers as $parser) {
if (!$parser instanceof Twig_TokenParserInterface) {
throw new LogicException('$parsers must a an array of Twig_TokenParserInterface');
}
$this->parsers[$parser->getTag()] = $parser;
}
foreach ($brokers as $broker) {
if (!$broker instanceof Twig_TokenParserBrokerInterface) {
throw new LogicException('$brokers must a an array of Twig_TokenParserBrokerInterface');
}
$this->brokers[] = $broker;
}
}
public function addTokenParser(Twig_TokenParserInterface $parser)
{
$this->parsers[$parser->getTag()] = $parser;
}
public function removeTokenParser(Twig_TokenParserInterface $parser)
{
$name = $parser->getTag();
if (isset($this->parsers[$name]) && $parser === $this->parsers[$name]) {
unset($this->parsers[$name]);
}
}
public function addTokenParserBroker(Twig_TokenParserBroker $broker)
{
$this->brokers[] = $broker;
}
public function removeTokenParserBroker(Twig_TokenParserBroker $broker)
{
if (false !== $pos = array_search($broker, $this->brokers)) {
unset($this->brokers[$pos]);
}
}
public function getTokenParser($tag)
{
if (isset($this->parsers[$tag])) {
return $this->parsers[$tag];
}
$broker = end($this->brokers);
while (false !== $broker) {
$parser = $broker->getTokenParser($tag);
if (null !== $parser) {
return $parser;
}
$broker = prev($this->brokers);
}
}
public function getParsers()
{
return $this->parsers;
}
public function getParser()
{
return $this->parser;
}
public function setParser(Twig_ParserInterface $parser)
{
$this->parser = $parser;
foreach ($this->parsers as $tokenParser) {
$tokenParser->setParser($parser);
}
foreach ($this->brokers as $broker) {
$broker->setParser($parser);
}
}
}
}
namespace
{
class Twig_TokenStream
{
protected $tokens;
protected $current;
protected $filename;
public function __construct(array $tokens, $filename = null)
{
$this->tokens = $tokens;
$this->current = 0;
$this->filename = $filename;
}
public function __toString()
{
return implode("\n", $this->tokens);
}
public function injectTokens(array $tokens)
{
$this->tokens = array_merge(array_slice($this->tokens, 0, $this->current), $tokens, array_slice($this->tokens, $this->current));
}
public function next()
{
if (!isset($this->tokens[++$this->current])) {
throw new Twig_Error_Syntax('Unexpected end of template', $this->tokens[$this->current - 1]->getLine(), $this->filename);
}
return $this->tokens[$this->current - 1];
}
public function expect($type, $value = null, $message = null)
{
$token = $this->tokens[$this->current];
if (!$token->test($type, $value)) {
$line = $token->getLine();
throw new Twig_Error_Syntax(sprintf('%sUnexpected token "%s" of value "%s" ("%s" expected%s)',
$message ? $message.'. ':'',
Twig_Token::typeToEnglish($token->getType(), $line), $token->getValue(),
Twig_Token::typeToEnglish($type, $line), $value ? sprintf(' with value "%s"', $value) :''),
$line,
$this->filename
);
}
$this->next();
return $token;
}
public function look($number = 1)
{
if (!isset($this->tokens[$this->current + $number])) {
throw new Twig_Error_Syntax('Unexpected end of template', $this->tokens[$this->current + $number - 1]->getLine(), $this->filename);
}
return $this->tokens[$this->current + $number];
}
public function test($primary, $secondary = null)
{
return $this->tokens[$this->current]->test($primary, $secondary);
}
public function isEOF()
{
return $this->tokens[$this->current]->getType() === Twig_Token::EOF_TYPE;
}
public function getCurrent()
{
return $this->tokens[$this->current];
}
public function getFilename()
{
return $this->filename;
}
}
}
namespace
{
class Twig_SimpleFilter
{
protected $name;
protected $callable;
protected $options;
protected $arguments = array();
public function __construct($name, $callable, array $options = array())
{
$this->name = $name;
$this->callable = $callable;
$this->options = array_merge(array('needs_environment'=> false,'needs_context'=> false,'is_safe'=> null,'is_safe_callback'=> null,'pre_escape'=> null,'preserves_safety'=> null,'node_class'=>'Twig_Node_Expression_Filter',
), $options);
}
public function getName()
{
return $this->name;
}
public function getCallable()
{
return $this->callable;
}
public function getNodeClass()
{
return $this->options['node_class'];
}
public function setArguments($arguments)
{
$this->arguments = $arguments;
}
public function getArguments()
{
return $this->arguments;
}
public function needsEnvironment()
{
return $this->options['needs_environment'];
}
public function needsContext()
{
return $this->options['needs_context'];
}
public function getSafe(Twig_Node $filterArgs)
{
if (null !== $this->options['is_safe']) {
return $this->options['is_safe'];
}
if (null !== $this->options['is_safe_callback']) {
return call_user_func($this->options['is_safe_callback'], $filterArgs);
}
}
public function getPreservesSafety()
{
return $this->options['preserves_safety'];
}
public function getPreEscape()
{
return $this->options['pre_escape'];
}
}
}
namespace
{
class Twig_SimpleFunction
{
protected $name;
protected $callable;
protected $options;
protected $arguments = array();
public function __construct($name, $callable, array $options = array())
{
$this->name = $name;
$this->callable = $callable;
$this->options = array_merge(array('needs_environment'=> false,'needs_context'=> false,'is_safe'=> null,'is_safe_callback'=> null,'node_class'=>'Twig_Node_Expression_Function',
), $options);
}
public function getName()
{
return $this->name;
}
public function getCallable()
{
return $this->callable;
}
public function getNodeClass()
{
return $this->options['node_class'];
}
public function setArguments($arguments)
{
$this->arguments = $arguments;
}
public function getArguments()
{
return $this->arguments;
}
public function needsEnvironment()
{
return $this->options['needs_environment'];
}
public function needsContext()
{
return $this->options['needs_context'];
}
public function getSafe(Twig_Node $functionArgs)
{
if (null !== $this->options['is_safe']) {
return $this->options['is_safe'];
}
if (null !== $this->options['is_safe_callback']) {
return call_user_func($this->options['is_safe_callback'], $functionArgs);
}
return array();
}
}
}
namespace
{
class Twig_SimpleTest
{
protected $name;
protected $callable;
protected $options;
public function __construct($name, $callable, array $options = array())
{
$this->name = $name;
$this->callable = $callable;
$this->options = array_merge(array('node_class'=>'Twig_Node_Expression_Test',
), $options);
}
public function getName()
{
return $this->name;
}
public function getCallable()
{
return $this->callable;
}
public function getNodeClass()
{
return $this->options['node_class'];
}
}
}
namespace {




use Symfony\Component\Console\Application;

use Sensio\Command\Build;

$console = new Application('Email-Makr', '0.1');

$c = new \Pimple();

$c['twig'] = $c->share($c->protect(function($templateDir) {
  $loader = new Twig_Loader_Filesystem($templateDir);
  $twig = new Twig_Environment($loader);

  return $twig;
}));

$console->add(new Build($c['twig']));

$console->run();
}
