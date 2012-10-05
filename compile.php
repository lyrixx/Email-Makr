#!/usr/bin/env php
<?php

// Compiles EmailMakr into one big PHP file
// Ported from Sismo https://github.com/fabpot/Sismo

use Symfony\Component\ClassLoader\ClassCollectionLoader;
use Symfony\Component\Finder\Finder;

require_once __DIR__.'/vendor/autoload.php';
@mkdir(__DIR__.'/build', 0777, true);
@unlink(__DIR__.'/build/emailmakr.php');

$classes = array (
    'Twig_Autoloader',
    'Twig_Compiler',
    'Twig_Environment',
    'Twig_Error',
    'Twig_Error_Loader',
    'Twig_Error_Runtime',
    'Twig_Error_Syntax',
    'Twig_ExpressionParser',
    'Twig_Extension_Core',
    'Twig_Extension_Debug',
    'Twig_Extension_Escaper',
    'Twig_Extension_Optimizer',
    'Twig_Extension_Sandbox',
    'Twig_Filter_Function',
    'Twig_Filter_Method',
    'Twig_Filter_Node',
    'Twig_Function_Function',
    'Twig_Function_Method',
    'Twig_Function_Node',
    'Twig_Lexer',
    'Twig_Loader_Array',
    'Twig_Loader_Chain',
    'Twig_Loader_Filesystem',
    'Twig_Loader_String',
    'Twig_Markup',
    'Twig_Node',
    'Twig_Node_AutoEscape',
    'Twig_Node_Block',
    'Twig_Node_BlockReference',
    'Twig_Node_Body',
    'Twig_Node_Do',
    'Twig_Node_Embed',
    'Twig_Node_Expression_Array',
    'Twig_Node_Expression_AssignName',
    'Twig_Node_Expression_Binary_Add',
    'Twig_Node_Expression_Binary_And',
    'Twig_Node_Expression_Binary_BitwiseAnd',
    'Twig_Node_Expression_Binary_BitwiseOr',
    'Twig_Node_Expression_Binary_BitwiseXor',
    'Twig_Node_Expression_Binary_Concat',
    'Twig_Node_Expression_Binary_Div',
    'Twig_Node_Expression_Binary_Equal',
    'Twig_Node_Expression_Binary_FloorDiv',
    'Twig_Node_Expression_Binary_Greater',
    'Twig_Node_Expression_Binary_GreaterEqual',
    'Twig_Node_Expression_Binary_In',
    'Twig_Node_Expression_Binary_Less',
    'Twig_Node_Expression_Binary_LessEqual',
    'Twig_Node_Expression_Binary_Mod',
    'Twig_Node_Expression_Binary_Mul',
    'Twig_Node_Expression_Binary_NotEqual',
    'Twig_Node_Expression_Binary_NotIn',
    'Twig_Node_Expression_Binary_Or',
    'Twig_Node_Expression_Binary_Power',
    'Twig_Node_Expression_Binary_Range',
    'Twig_Node_Expression_Binary_Sub',
    'Twig_Node_Expression_BlockReference',
    'Twig_Node_Expression_Conditional',
    'Twig_Node_Expression_Constant',
    'Twig_Node_Expression_ExtensionReference',
    'Twig_Node_Expression_Filter',
    'Twig_Node_Expression_Filter_Default',
    'Twig_Node_Expression_Function',
    'Twig_Node_Expression_GetAttr',
    'Twig_Node_Expression_MethodCall',
    'Twig_Node_Expression_Name',
    'Twig_Node_Expression_Parent',
    'Twig_Node_Expression_TempName',
    'Twig_Node_Expression_Test',
    'Twig_Node_Expression_Test_Constant',
    'Twig_Node_Expression_Test_Defined',
    'Twig_Node_Expression_Test_Divisibleby',
    'Twig_Node_Expression_Test_Even',
    'Twig_Node_Expression_Test_Null',
    'Twig_Node_Expression_Test_Odd',
    'Twig_Node_Expression_Test_Sameas',
    'Twig_Node_Expression_Unary_Neg',
    'Twig_Node_Expression_Unary_Not',
    'Twig_Node_Expression_Unary_Pos',
    'Twig_Node_Flush',
    'Twig_Node_For',
    'Twig_Node_ForLoop',
    'Twig_Node_If',
    'Twig_Node_Import',
    'Twig_Node_Include',
    'Twig_Node_Macro',
    'Twig_Node_Module',
    'Twig_Node_Print',
    'Twig_Node_Sandbox',
    'Twig_Node_SandboxedModule',
    'Twig_Node_SandboxedPrint',
    'Twig_Node_Set',
    'Twig_Node_SetTemp',
    'Twig_Node_Spaceless',
    'Twig_Node_Text',
    'Twig_NodeTraverser',
    'Twig_NodeVisitor_Escaper',
    'Twig_NodeVisitor_Optimizer',
    'Twig_NodeVisitor_SafeAnalysis',
    'Twig_NodeVisitor_Sandbox',
    'Twig_Parser',
    'Twig_Sandbox_SecurityError',
    'Twig_Sandbox_SecurityPolicy',
    'Twig_Template',
    'Twig_Token',
    'Twig_TokenParser_AutoEscape',
    'Twig_TokenParser_Block',
    'Twig_TokenParser_Do',
    'Twig_TokenParser_Embed',
    'Twig_TokenParser_Extends',
    'Twig_TokenParser_Filter',
    'Twig_TokenParser_Flush',
    'Twig_TokenParser_For',
    'Twig_TokenParser_From',
    'Twig_TokenParser_If',
    'Twig_TokenParser_Import',
    'Twig_TokenParser_Include',
    'Twig_TokenParser_Macro',
    'Twig_TokenParser_Sandbox',
    'Twig_TokenParser_Set',
    'Twig_TokenParser_Spaceless',
    'Twig_TokenParser_Use',
    'Twig_TokenParserBroker',
    'Twig_TokenStream',
    'Symfony\Component\Console\Application',
    'Symfony\Component\Console\Command\Command',
    'Symfony\Component\Console\Command\HelpCommand',
    'Symfony\Component\Console\Command\ListCommand',
    'Symfony\Component\Console\Formatter\OutputFormatter',
    'Symfony\Component\Console\Formatter\OutputFormatterStyle',
    'Symfony\Component\Console\Formatter\OutputFormatterStyleStack',
    'Symfony\Component\Console\Helper\DialogHelper',
    'Symfony\Component\Console\Helper\FormatterHelper',
    'Symfony\Component\Console\Helper\Helper',
    'Symfony\Component\Console\Helper\HelperSet',
    'Symfony\Component\Console\Input\ArgvInput',
    'Symfony\Component\Console\Input\ArrayInput',
    'Symfony\Component\Console\Input\Input',
    'Symfony\Component\Console\Input\InputArgument',
    'Symfony\Component\Console\Input\InputDefinition',
    'Symfony\Component\Console\Input\InputOption',
    'Symfony\Component\Console\Input\StringInput',
    'Symfony\Component\Console\Output\ConsoleOutput',
    'Symfony\Component\Console\Output\NullOutput',
    'Symfony\Component\Console\Output\Output',
    'Symfony\Component\Console\Output\StreamOutput',
    'Symfony\Component\Console\Shell',
    'Pimple',
    'Sensio\Command\Build',
);

$ccl = new ClassCollectionLoader();
$ccl->load($classes, __DIR__.'/build', 'emailmakr', false);

$classes = str_replace('<?php', '', file_get_contents(__DIR__.'/build/emailmakr.php'));
$classes = str_replace("eval('?>'.", 'eval(', $classes);

$app = 'namespace {'.str_replace('<?php', '', file_get_contents(__DIR__.'/emailmakr')).'}';
$app = str_replace('#!/usr/bin/env php', '', $app);

$content = "#!/usr/bin/env php
<?php
$classes
$app
";

// remove require_once calls
$content = preg_replace('#require_once[^;]+?;#', '', $content);

file_put_contents(__DIR__.'/build/emailmakr.php', $content);

@chmod(__DIR__.'/build/emailmakr.php', 0755);
