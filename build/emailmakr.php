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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Autoloads Twig classes.
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
class Twig_Autoloader
{
    /**
     * Registers Twig_Autoloader as an SPL autoloader.
     */
    static public function register()
    {
        ini_set('unserialize_callback_func', 'spl_autoload_call');
        spl_autoload_register(array(new self, 'autoload'));
    }
    /**
     * Handles autoloading of classes.
     *
     * @param string $class A class name.
     */
    static public function autoload($class)
    {
        if (0 !== strpos($class, 'Twig')) {
            return;
        }
        if (is_file($file = dirname(__FILE__).'/../'.str_replace(array('_', "\0"), array('/', ''), $class).'.php')) {
            require $file;
        }
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Interface implemented by compiler classes.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
interface Twig_CompilerInterface
{
    /**
     * Compiles a node.
     *
     * @param Twig_NodeInterface $node The node to compile
     *
     * @return Twig_CompilerInterface The current compiler instance
     */
    function compile(Twig_NodeInterface $node);
    /**
     * Gets the current PHP code after compilation.
     *
     * @return string The PHP code
     */
    function getSource();
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Compiles a node to PHP code.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Compiler implements Twig_CompilerInterface
{
    protected $lastLine;
    protected $source;
    protected $indentation;
    protected $env;
    protected $debugInfo;
    protected $sourceOffset;
    protected $sourceLine;
    /**
     * Constructor.
     *
     * @param Twig_Environment $env The twig environment instance
     */
    public function __construct(Twig_Environment $env)
    {
        $this->env = $env;
        $this->debugInfo = array();
    }
    /**
     * Returns the environment instance related to this compiler.
     *
     * @return Twig_Environment The environment instance
     */
    public function getEnvironment()
    {
        return $this->env;
    }
    /**
     * Gets the current PHP code after compilation.
     *
     * @return string The PHP code
     */
    public function getSource()
    {
        return $this->source;
    }
    /**
     * Compiles a node.
     *
     * @param Twig_NodeInterface $node        The node to compile
     * @param integer            $indentation The current indentation
     *
     * @return Twig_Compiler The current compiler instance
     */
    public function compile(Twig_NodeInterface $node, $indentation = 0)
    {
        $this->lastLine = null;
        $this->source = '';
        $this->sourceOffset = 0;
        $this->sourceLine = 0;
        $this->indentation = $indentation;
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
    /**
     * Adds a raw string to the compiled code.
     *
     * @param string $string The string
     *
     * @return Twig_Compiler The current compiler instance
     */
    public function raw($string)
    {
        $this->source .= $string;
        return $this;
    }
    /**
     * Writes a string to the compiled code by adding indentation.
     *
     * @return Twig_Compiler The current compiler instance
     */
    public function write()
    {
        $strings = func_get_args();
        foreach ($strings as $string) {
            $this->addIndentation();
            $this->source .= $string;
        }
        return $this;
    }
    /**
     * Appends an indentation to the current PHP code after compilation.
     *
     * @return Twig_Compiler The current compiler instance
     */
    public function addIndentation()
    {
        $this->source .= str_repeat(' ', $this->indentation * 4);
        return $this;
    }
    /**
     * Adds a quoted string to the compiled code.
     *
     * @param string $value The string
     *
     * @return Twig_Compiler The current compiler instance
     */
    public function string($value)
    {
        $this->source .= sprintf('"%s"', addcslashes($value, "\0\t\"\$\\"));
        return $this;
    }
    /**
     * Returns a PHP representation of a given value.
     *
     * @param mixed $value The value to convert
     *
     * @return Twig_Compiler The current compiler instance
     */
    public function repr($value)
    {
        if (is_int($value) || is_float($value)) {
            if (false !== $locale = setlocale(LC_NUMERIC, 0)) {
                setlocale(LC_NUMERIC, 'C');
            }
            $this->raw($value);
            if (false !== $locale) {
                setlocale(LC_NUMERIC, $locale);
            }
        } elseif (null === $value) {
            $this->raw('null');
        } elseif (is_bool($value)) {
            $this->raw($value ? 'true' : 'false');
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
    /**
     * Adds debugging information.
     *
     * @param Twig_NodeInterface $node The related twig node
     *
     * @return Twig_Compiler The current compiler instance
     */
    public function addDebugInfo(Twig_NodeInterface $node)
    {
        if ($node->getLine() != $this->lastLine) {
            // when mbstring.func_overload is set to 2
            // mb_substr_count() replaces substr_count()
            // but they have different signatures!
            if (((int) ini_get('mbstring.func_overload')) & 2) {
                // this is much slower than the "right" version
                $this->sourceLine += mb_substr_count(mb_substr($this->source, $this->sourceOffset), "\n");
            } else {
                $this->sourceLine += substr_count($this->source, "\n", $this->sourceOffset);
            }
            $this->sourceOffset = strlen($this->source);
            $this->debugInfo[$this->sourceLine] = $node->getLine();
            $this->lastLine = $node->getLine();
            $this->write("// line {$node->getLine()}\n");
        }
        return $this;
    }
    public function getDebugInfo()
    {
        return $this->debugInfo;
    }
    /**
     * Indents the generated code.
     *
     * @param integer $step The number of indentation to add
     *
     * @return Twig_Compiler The current compiler instance
     */
    public function indent($step = 1)
    {
        $this->indentation += $step;
        return $this;
    }
    /**
     * Outdents the generated code.
     *
     * @param integer $step The number of indentation to remove
     *
     * @return Twig_Compiler The current compiler instance
     */
    public function outdent($step = 1)
    {
        // can't outdent by more steps that the current indentation level
        if ($this->indentation < $step) {
            throw new Twig_Error('Unable to call outdent() as the indentation would become negative');
        }
        $this->indentation -= $step;
        return $this;
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Stores the Twig configuration.
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
class Twig_Environment
{
    const VERSION = '1.9.3-DEV';
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
    protected $loadedTemplates;
    protected $strictVariables;
    protected $unaryOperators;
    protected $binaryOperators;
    protected $templateClassPrefix = '__TwigTemplate_';
    protected $functionCallbacks;
    protected $filterCallbacks;
    protected $staging;
    /**
     * Constructor.
     *
     * Available options:
     *
     *  * debug: When set to true, it automatically set "auto_reload" to true as
     *           well (default to false).
     *
     *  * charset: The charset used by the templates (default to utf-8).
     *
     *  * base_template_class: The base template class to use for generated
     *                         templates (default to Twig_Template).
     *
     *  * cache: An absolute path where to store the compiled templates, or
     *           false to disable compilation cache (default).
     *
     *  * auto_reload: Whether to reload the template is the original source changed.
     *                 If you don't provide the auto_reload option, it will be
     *                 determined automatically base on the debug value.
     *
     *  * strict_variables: Whether to ignore invalid variables in templates
     *                      (default to false).
     *
     *  * autoescape: Whether to enable auto-escaping (default to html):
     *                  * false: disable auto-escaping
     *                  * true: equivalent to html
     *                  * html, js: set the autoescaping to one of the supported strategies
     *                  * PHP callback: a PHP callback that returns an escaping strategy based on the template "filename"
     *
     *  * optimizations: A flag that indicates which optimizations to apply
     *                   (default to -1 which means that all optimizations are enabled;
     *                   set it to 0 to disable).
     *
     * @param Twig_LoaderInterface $loader  A Twig_LoaderInterface instance
     * @param array                $options An array of options
     */
    public function __construct(Twig_LoaderInterface $loader = null, $options = array())
    {
        if (null !== $loader) {
            $this->setLoader($loader);
        }
        $options = array_merge(array(
            'debug'               => false,
            'charset'             => 'UTF-8',
            'base_template_class' => 'Twig_Template',
            'strict_variables'    => false,
            'autoescape'          => 'html',
            'cache'               => false,
            'auto_reload'         => null,
            'optimizations'       => -1,
        ), $options);
        $this->debug              = (bool) $options['debug'];
        $this->charset            = $options['charset'];
        $this->baseTemplateClass  = $options['base_template_class'];
        $this->autoReload         = null === $options['auto_reload'] ? $this->debug : (bool) $options['auto_reload'];
        $this->extensions         = array(
            'core'      => new Twig_Extension_Core(),
            'escaper'   => new Twig_Extension_Escaper($options['autoescape']),
            'optimizer' => new Twig_Extension_Optimizer($options['optimizations']),
        );
        $this->strictVariables    = (bool) $options['strict_variables'];
        $this->runtimeInitialized = false;
        $this->setCache($options['cache']);
        $this->functionCallbacks = array();
        $this->filterCallbacks = array();
        $this->staging = array(
            'functions'     => array(),
            'filters'       => array(),
            'tests'         => array(),
            'token_parsers' => array(),
            'visitors'      => array(),
            'globals'       => array(),
        );
    }
    /**
     * Gets the base template class for compiled templates.
     *
     * @return string The base template class name
     */
    public function getBaseTemplateClass()
    {
        return $this->baseTemplateClass;
    }
    /**
     * Sets the base template class for compiled templates.
     *
     * @param string $class The base template class name
     */
    public function setBaseTemplateClass($class)
    {
        $this->baseTemplateClass = $class;
    }
    /**
     * Enables debugging mode.
     */
    public function enableDebug()
    {
        $this->debug = true;
    }
    /**
     * Disables debugging mode.
     */
    public function disableDebug()
    {
        $this->debug = false;
    }
    /**
     * Checks if debug mode is enabled.
     *
     * @return Boolean true if debug mode is enabled, false otherwise
     */
    public function isDebug()
    {
        return $this->debug;
    }
    /**
     * Enables the auto_reload option.
     */
    public function enableAutoReload()
    {
        $this->autoReload = true;
    }
    /**
     * Disables the auto_reload option.
     */
    public function disableAutoReload()
    {
        $this->autoReload = false;
    }
    /**
     * Checks if the auto_reload option is enabled.
     *
     * @return Boolean true if auto_reload is enabled, false otherwise
     */
    public function isAutoReload()
    {
        return $this->autoReload;
    }
    /**
     * Enables the strict_variables option.
     */
    public function enableStrictVariables()
    {
        $this->strictVariables = true;
    }
    /**
     * Disables the strict_variables option.
     */
    public function disableStrictVariables()
    {
        $this->strictVariables = false;
    }
    /**
     * Checks if the strict_variables option is enabled.
     *
     * @return Boolean true if strict_variables is enabled, false otherwise
     */
    public function isStrictVariables()
    {
        return $this->strictVariables;
    }
    /**
     * Gets the cache directory or false if cache is disabled.
     *
     * @return string|false
     */
    public function getCache()
    {
        return $this->cache;
    }
     /**
      * Sets the cache directory or false if cache is disabled.
      *
      * @param string|false $cache The absolute path to the compiled templates,
      *                            or false to disable cache
      */
    public function setCache($cache)
    {
        $this->cache = $cache ? $cache : false;
    }
    /**
     * Gets the cache filename for a given template.
     *
     * @param string $name The template name
     *
     * @return string The cache file name
     */
    public function getCacheFilename($name)
    {
        if (false === $this->cache) {
            return false;
        }
        $class = substr($this->getTemplateClass($name), strlen($this->templateClassPrefix));
        return $this->getCache().'/'.substr($class, 0, 2).'/'.substr($class, 2, 2).'/'.substr($class, 4).'.php';
    }
    /**
     * Gets the template class associated with the given string.
     *
     * @param string  $name  The name for which to calculate the template class name
     * @param integer $index The index if it is an embedded template
     *
     * @return string The template class name
     */
    public function getTemplateClass($name, $index = null)
    {
        return $this->templateClassPrefix.md5($this->loader->getCacheKey($name)).(null === $index ? '' : '_'.$index);
    }
    /**
     * Gets the template class prefix.
     *
     * @return string The template class prefix
     */
    public function getTemplateClassPrefix()
    {
        return $this->templateClassPrefix;
    }
    /**
     * Renders a template.
     *
     * @param string $name    The template name
     * @param array  $context An array of parameters to pass to the template
     *
     * @return string The rendered template
     */
    public function render($name, array $context = array())
    {
        return $this->loadTemplate($name)->render($context);
    }
    /**
     * Displays a template.
     *
     * @param string $name    The template name
     * @param array  $context An array of parameters to pass to the template
     */
    public function display($name, array $context = array())
    {
        $this->loadTemplate($name)->display($context);
    }
    /**
     * Loads a template by name.
     *
     * @param string  $name  The template name
     * @param integer $index The index if it is an embedded template
     *
     * @return Twig_TemplateInterface A template instance representing the given template name
     */
    public function loadTemplate($name, $index = null)
    {
        $cls = $this->getTemplateClass($name, $index);
        if (isset($this->loadedTemplates[$cls])) {
            return $this->loadedTemplates[$cls];
        }
        if (!class_exists($cls, false)) {
            if (false === $cache = $this->getCacheFilename($name)) {
                eval($this->compileSource($this->loader->getSource($name), $name));
            } else {
                if (!is_file($cache) || ($this->isAutoReload() && !$this->isTemplateFresh($name, filemtime($cache)))) {
                    $this->writeCacheFile($cache, $this->compileSource($this->loader->getSource($name), $name));
                }
                
            }
        }
        if (!$this->runtimeInitialized) {
            $this->initRuntime();
        }
        return $this->loadedTemplates[$cls] = new $cls($this);
    }
    /**
     * Returns true if the template is still fresh.
     *
     * Besides checking the loader for freshness information,
     * this method also checks if the enabled extensions have
     * not changed.
     *
     * @param string    $name The template name
     * @param timestamp $time The last modification time of the cached template
     *
     * @return Boolean true if the template is fresh, false otherwise
     */
    public function isTemplateFresh($name, $time)
    {
        foreach ($this->extensions as $extension) {
            $r = new ReflectionObject($extension);
            if (filemtime($r->getFileName()) > $time) {
                return false;
            }
        }
        return $this->loader->isFresh($name, $time);
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
    /**
     * Clears the internal template cache.
     */
    public function clearTemplateCache()
    {
        $this->loadedTemplates = array();
    }
    /**
     * Clears the template cache files on the filesystem.
     */
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
    /**
     * Gets the Lexer instance.
     *
     * @return Twig_LexerInterface A Twig_LexerInterface instance
     */
    public function getLexer()
    {
        if (null === $this->lexer) {
            $this->lexer = new Twig_Lexer($this);
        }
        return $this->lexer;
    }
    /**
     * Sets the Lexer instance.
     *
     * @param Twig_LexerInterface A Twig_LexerInterface instance
     */
    public function setLexer(Twig_LexerInterface $lexer)
    {
        $this->lexer = $lexer;
    }
    /**
     * Tokenizes a source code.
     *
     * @param string $source The template source code
     * @param string $name   The template name
     *
     * @return Twig_TokenStream A Twig_TokenStream instance
     */
    public function tokenize($source, $name = null)
    {
        return $this->getLexer()->tokenize($source, $name);
    }
    /**
     * Gets the Parser instance.
     *
     * @return Twig_ParserInterface A Twig_ParserInterface instance
     */
    public function getParser()
    {
        if (null === $this->parser) {
            $this->parser = new Twig_Parser($this);
        }
        return $this->parser;
    }
    /**
     * Sets the Parser instance.
     *
     * @param Twig_ParserInterface A Twig_ParserInterface instance
     */
    public function setParser(Twig_ParserInterface $parser)
    {
        $this->parser = $parser;
    }
    /**
     * Parses a token stream.
     *
     * @param Twig_TokenStream $tokens A Twig_TokenStream instance
     *
     * @return Twig_Node_Module A Node tree
     */
    public function parse(Twig_TokenStream $tokens)
    {
        return $this->getParser()->parse($tokens);
    }
    /**
     * Gets the Compiler instance.
     *
     * @return Twig_CompilerInterface A Twig_CompilerInterface instance
     */
    public function getCompiler()
    {
        if (null === $this->compiler) {
            $this->compiler = new Twig_Compiler($this);
        }
        return $this->compiler;
    }
    /**
     * Sets the Compiler instance.
     *
     * @param Twig_CompilerInterface $compiler A Twig_CompilerInterface instance
     */
    public function setCompiler(Twig_CompilerInterface $compiler)
    {
        $this->compiler = $compiler;
    }
    /**
     * Compiles a Node.
     *
     * @param Twig_NodeInterface $node A Twig_NodeInterface instance
     *
     * @return string The compiled PHP source code
     */
    public function compile(Twig_NodeInterface $node)
    {
        return $this->getCompiler()->compile($node)->getSource();
    }
    /**
     * Compiles a template source code.
     *
     * @param string $source The template source code
     * @param string $name   The template name
     *
     * @return string The compiled PHP source code
     */
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
    /**
     * Sets the Loader instance.
     *
     * @param Twig_LoaderInterface $loader A Twig_LoaderInterface instance
     */
    public function setLoader(Twig_LoaderInterface $loader)
    {
        $this->loader = $loader;
    }
    /**
     * Gets the Loader instance.
     *
     * @return Twig_LoaderInterface A Twig_LoaderInterface instance
     */
    public function getLoader()
    {
        return $this->loader;
    }
    /**
     * Sets the default template charset.
     *
     * @param string $charset The default charset
     */
    public function setCharset($charset)
    {
        $this->charset = $charset;
    }
    /**
     * Gets the default template charset.
     *
     * @return string The default charset
     */
    public function getCharset()
    {
        return $this->charset;
    }
    /**
     * Initializes the runtime environment.
     */
    public function initRuntime()
    {
        $this->runtimeInitialized = true;
        foreach ($this->getExtensions() as $extension) {
            $extension->initRuntime($this);
        }
    }
    /**
     * Returns true if the given extension is registered.
     *
     * @param string $name The extension name
     *
     * @return Boolean Whether the extension is registered or not
     */
    public function hasExtension($name)
    {
        return isset($this->extensions[$name]);
    }
    /**
     * Gets an extension by name.
     *
     * @param string $name The extension name
     *
     * @return Twig_ExtensionInterface A Twig_ExtensionInterface instance
     */
    public function getExtension($name)
    {
        if (!isset($this->extensions[$name])) {
            throw new Twig_Error_Runtime(sprintf('The "%s" extension is not enabled.', $name));
        }
        return $this->extensions[$name];
    }
    /**
     * Registers an extension.
     *
     * @param Twig_ExtensionInterface $extension A Twig_ExtensionInterface instance
     */
    public function addExtension(Twig_ExtensionInterface $extension)
    {
        $this->extensions[$extension->getName()] = $extension;
        $this->parsers = null;
        $this->visitors = null;
        $this->filters = null;
        $this->tests = null;
        $this->functions = null;
        $this->globals = null;
    }
    /**
     * Removes an extension by name.
     *
     * @param string $name The extension name
     */
    public function removeExtension($name)
    {
        unset($this->extensions[$name]);
        $this->parsers = null;
        $this->visitors = null;
        $this->filters = null;
        $this->tests = null;
        $this->functions = null;
        $this->globals = null;
    }
    /**
     * Registers an array of extensions.
     *
     * @param array $extensions An array of extensions
     */
    public function setExtensions(array $extensions)
    {
        foreach ($extensions as $extension) {
            $this->addExtension($extension);
        }
    }
    /**
     * Returns all registered extensions.
     *
     * @return array An array of extensions
     */
    public function getExtensions()
    {
        return $this->extensions;
    }
    /**
     * Registers a Token Parser.
     *
     * @param Twig_TokenParserInterface $parser A Twig_TokenParserInterface instance
     */
    public function addTokenParser(Twig_TokenParserInterface $parser)
    {
        $this->staging['token_parsers'][] = $parser;
        $this->parsers = null;
    }
    /**
     * Gets the registered Token Parsers.
     *
     * @return Twig_TokenParserBrokerInterface A broker containing token parsers
     */
    public function getTokenParsers()
    {
        if (null === $this->parsers) {
            $this->parsers = new Twig_TokenParserBroker();
            if (isset($this->staging['token_parsers'])) {
                foreach ($this->staging['token_parsers'] as $parser) {
                    $this->parsers->addTokenParser($parser);
                }
            }
            foreach ($this->getExtensions() as $extension) {
                $parsers = $extension->getTokenParsers();
                foreach ($parsers as $parser) {
                    if ($parser instanceof Twig_TokenParserInterface) {
                        $this->parsers->addTokenParser($parser);
                    } elseif ($parser instanceof Twig_TokenParserBrokerInterface) {
                        $this->parsers->addTokenParserBroker($parser);
                    } else {
                        throw new Twig_Error_Runtime('getTokenParsers() must return an array of Twig_TokenParserInterface or Twig_TokenParserBrokerInterface instances');
                    }
                }
            }
        }
        return $this->parsers;
    }
    /**
     * Gets registered tags.
     *
     * Be warned that this method cannot return tags defined by Twig_TokenParserBrokerInterface classes.
     *
     * @return Twig_TokenParserInterface[] An array of Twig_TokenParserInterface instances
     */
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
    /**
     * Registers a Node Visitor.
     *
     * @param Twig_NodeVisitorInterface $visitor A Twig_NodeVisitorInterface instance
     */
    public function addNodeVisitor(Twig_NodeVisitorInterface $visitor)
    {
        $this->staging['visitors'][] = $visitor;
        $this->visitors = null;
    }
    /**
     * Gets the registered Node Visitors.
     *
     * @return Twig_NodeVisitorInterface[] An array of Twig_NodeVisitorInterface instances
     */
    public function getNodeVisitors()
    {
        if (null === $this->visitors) {
            foreach ($this->getExtensions() as $extension) {
                foreach ($extension->getNodeVisitors() as $visitor) {
                    $this->addNodeVisitor($visitor);
                }
            }
            $this->visitors = $this->staging['visitors'];
        }
        return $this->visitors;
    }
    /**
     * Registers a Filter.
     *
     * @param string               $name   The filter name
     * @param Twig_FilterInterface $filter A Twig_FilterInterface instance
     */
    public function addFilter($name, Twig_FilterInterface $filter)
    {
        $this->staging['filters'][$name] = $filter;
        $this->filters = null;
    }
    /**
     * Get a filter by name.
     *
     * Subclasses may override this method and load filters differently;
     * so no list of filters is available.
     *
     * @param string $name The filter name
     *
     * @return Twig_Filter|false A Twig_Filter instance or false if the filter does not exists
     */
    public function getFilter($name)
    {
        if (null === $this->filters) {
            $this->getFilters();
        }
        if (isset($this->filters[$name])) {
            return $this->filters[$name];
        }
        foreach ($this->filters as $pattern => $filter) {
            $pattern = str_replace('\\*', '(.*?)', preg_quote($pattern, '#'), $count);
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
    /**
     * Gets the registered Filters.
     *
     * Be warned that this method cannot return filters defined with registerUndefinedFunctionCallback.
     *
     * @return Twig_FilterInterface[] An array of Twig_FilterInterface instances
     *
     * @see registerUndefinedFilterCallback
     */
    public function getFilters()
    {
        if (null === $this->filters) {
            foreach ($this->getExtensions() as $extension) {
                foreach ($extension->getFilters() as $name => $filter) {
                    $this->addFilter($name, $filter);
                }
            }
            $this->filters = $this->staging['filters'];
        }
        return $this->filters;
    }
    /**
     * Registers a Test.
     *
     * @param string             $name The test name
     * @param Twig_TestInterface $test A Twig_TestInterface instance
     */
    public function addTest($name, Twig_TestInterface $test)
    {
        $this->staging['tests'][$name] = $test;
        $this->tests = null;
    }
    /**
     * Gets the registered Tests.
     *
     * @return Twig_TestInterface[] An array of Twig_TestInterface instances
     */
    public function getTests()
    {
        if (null === $this->tests) {
            foreach ($this->getExtensions() as $extension) {
                foreach ($extension->getTests() as $name => $test) {
                    $this->addTest($name, $test);
                }
            }
            $this->tests = $this->staging['tests'];
        }
        return $this->tests;
    }
    /**
     * Registers a Function.
     *
     * @param string                 $name     The function name
     * @param Twig_FunctionInterface $function A Twig_FunctionInterface instance
     */
    public function addFunction($name, Twig_FunctionInterface $function)
    {
        $this->staging['functions'][$name] = $function;
        $this->functions = null;
    }
    /**
     * Get a function by name.
     *
     * Subclasses may override this method and load functions differently;
     * so no list of functions is available.
     *
     * @param string $name function name
     *
     * @return Twig_Function|false A Twig_Function instance or false if the function does not exists
     */
    public function getFunction($name)
    {
        if (null === $this->functions) {
            $this->getFunctions();
        }
        if (isset($this->functions[$name])) {
            return $this->functions[$name];
        }
        foreach ($this->functions as $pattern => $function) {
            $pattern = str_replace('\\*', '(.*?)', preg_quote($pattern, '#'), $count);
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
    /**
     * Gets registered functions.
     *
     * Be warned that this method cannot return functions defined with registerUndefinedFunctionCallback.
     *
     * @return Twig_FunctionInterface[] An array of Twig_FunctionInterface instances
     *
     * @see registerUndefinedFunctionCallback
     */
    public function getFunctions()
    {
        if (null === $this->functions) {
            foreach ($this->getExtensions() as $extension) {
                foreach ($extension->getFunctions() as $name => $function) {
                    $this->addFunction($name, $function);
                }
            }
            $this->functions = $this->staging['functions'];
        }
        return $this->functions;
    }
    /**
     * Registers a Global.
     *
     * @param string $name  The global name
     * @param mixed  $value The global value
     */
    public function addGlobal($name, $value)
    {
        $this->staging['globals'][$name] = $value;
        $this->globals = null;
    }
    /**
     * Gets the registered Globals.
     *
     * @return array An array of globals
     */
    public function getGlobals()
    {
        if (null === $this->globals) {
            $this->globals = isset($this->staging['globals']) ? $this->staging['globals'] : array();
            foreach ($this->getExtensions() as $extension) {
                $this->globals = array_merge($this->globals, $extension->getGlobals());
            }
        }
        return $this->globals;
    }
    /**
     * Merges a context with the defined globals.
     *
     * @param array $context An array representing the context
     *
     * @return array The context merged with the globals
     */
    public function mergeGlobals(array $context)
    {
        // we don't use array_merge as the context being generally
        // bigger than globals, this code is faster.
        foreach ($this->getGlobals() as $key => $value) {
            if (!array_key_exists($key, $context)) {
                $context[$key] = $value;
            }
        }
        return $context;
    }
    /**
     * Gets the registered unary Operators.
     *
     * @return array An array of unary operators
     */
    public function getUnaryOperators()
    {
        if (null === $this->unaryOperators) {
            $this->initOperators();
        }
        return $this->unaryOperators;
    }
    /**
     * Gets the registered binary Operators.
     *
     * @return array An array of binary operators
     */
    public function getBinaryOperators()
    {
        if (null === $this->binaryOperators) {
            $this->initOperators();
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
    protected function initOperators()
    {
        $this->unaryOperators = array();
        $this->binaryOperators = array();
        foreach ($this->getExtensions() as $extension) {
            $operators = $extension->getOperators();
            if (!$operators) {
                continue;
            }
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
            // rename does not work on Win32 before 5.2.6
            if (@rename($tmpFile, $file) || (@copy($tmpFile, $file) && unlink($tmpFile))) {
                @chmod($file, 0666 & ~umask());
                return;
            }
        }
        throw new Twig_Error_Runtime(sprintf('Failed to write cache file "%s".', $file));
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Twig base exception.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Error extends Exception
{
    protected $lineno;
    protected $filename;
    protected $rawMessage;
    protected $previous;
    /**
     * Constructor.
     *
     * @param string    $message  The error message
     * @param integer   $lineno   The template line where the error occurred
     * @param string    $filename The template file name where the error occurred
     * @param Exception $previous The previous exception
     */
    public function __construct($message, $lineno = -1, $filename = null, Exception $previous = null)
    {
        if (version_compare(PHP_VERSION, '5.3.0', '<')) {
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
    /**
     * Gets the raw message.
     *
     * @return string The raw message
     */
    public function getRawMessage()
    {
        return $this->rawMessage;
    }
    /**
     * Gets the filename where the error occurred.
     *
     * @return string The filename
     */
    public function getTemplateFile()
    {
        return $this->filename;
    }
    /**
     * Sets the filename where the error occurred.
     *
     * @param string $filename The filename
     */
    public function setTemplateFile($filename)
    {
        $this->filename = $filename;
        $this->updateRepr();
    }
    /**
     * Gets the template line where the error occurred.
     *
     * @return integer The template line
     */
    public function getTemplateLine()
    {
        return $this->lineno;
    }
    /**
     * Sets the template line where the error occurred.
     *
     * @param integer $lineno The template line
     */
    public function setTemplateLine($lineno)
    {
        $this->lineno = $lineno;
        $this->updateRepr();
    }
    /**
     * For PHP < 5.3.0, provides access to the getPrevious() method.
     *
     * @param string $method    The method name
     * @param array  $arguments The parameters to be passed to the method
     *
     * @return Exception The previous exception or null
     */
    public function __call($method, $arguments)
    {
        if ('getprevious' == strtolower($method)) {
            return $this->previous;
        }
        throw new BadMethodCallException(sprintf('Method "Twig_Error::%s()" does not exist.', $method));
    }
    protected function updateRepr()
    {
        $this->message = $this->rawMessage;
        $dot = false;
        if ('.' === substr($this->message, -1)) {
            $this->message = substr($this->message, 0, -1);
            $dot = true;
        }
        if (null !== $this->filename) {
            if (is_string($this->filename) || (is_object($this->filename) && method_exists($this->filename, '__toString'))) {
                $filename = sprintf('"%s"', $this->filename);
            } else {
                $filename = json_encode($this->filename);
            }
            $this->message .= sprintf(' in %s', $filename);
        }
        if ($this->lineno >= 0) {
            $this->message .= sprintf(' at line %d', $this->lineno);
        }
        if ($dot) {
            $this->message .= '.';
        }
    }
    protected function guessTemplateInfo()
    {
        $template = null;
        foreach (debug_backtrace() as $trace) {
            if (isset($trace['object']) && $trace['object'] instanceof Twig_Template && 'Twig_Template' !== get_class($trace['object'])) {
                $template = $trace['object'];
            }
        }
        // update template filename
        if (null !== $template && null === $this->filename) {
            $this->filename = $template->getTemplateName();
        }
        if (null === $template || $this->lineno > -1) {
            return;
        }
        $r = new ReflectionObject($template);
        $file = $r->getFileName();
        $exceptions = array($e = $this);
        while (($e instanceof self || method_exists($e, 'getPrevious')) && $e = $e->getPrevious()) {
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
                        // update template line
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Exception thrown when an error occurs during template loading.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Error_Loader extends Twig_Error
{
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Exception thrown when an error occurs at runtime.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Error_Runtime extends Twig_Error
{
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Exception thrown when a syntax error occurs during lexing or parsing of a template.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Error_Syntax extends Twig_Error
{
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Parses expressions.
 *
 * This parser implements a "Precedence climbing" algorithm.
 *
 * @see http://www.engr.mun.ca/~theo/Misc/exp_parsing.htm
 * @see http://en.wikipedia.org/wiki/Operator-precedence_parser
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
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
        } elseif ($token->test(Twig_Token::PUNCTUATION_TYPE, '(')) {
            $this->parser->getStream()->next();
            $expr = $this->parseExpression();
            $this->parser->getStream()->expect(Twig_Token::PUNCTUATION_TYPE, ')', 'An opened parenthesis is not properly closed');
            return $this->parsePostfixExpression($expr);
        }
        return $this->parsePrimaryExpression();
    }
    protected function parseConditionalExpression($expr)
    {
        while ($this->parser->getStream()->test(Twig_Token::PUNCTUATION_TYPE, '?')) {
            $this->parser->getStream()->next();
            $expr2 = $this->parseExpression();
            $this->parser->getStream()->expect(Twig_Token::PUNCTUATION_TYPE, ':', 'The ternary operator must have a default value');
            $expr3 = $this->parseExpression();
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
                    case 'true':
                    case 'TRUE':
                        $node = new Twig_Node_Expression_Constant(true, $token->getLine());
                        break;
                    case 'false':
                    case 'FALSE':
                        $node = new Twig_Node_Expression_Constant(false, $token->getLine());
                        break;
                    case 'none':
                    case 'NONE':
                    case 'null':
                    case 'NULL':
                        $node = new Twig_Node_Expression_Constant(null, $token->getLine());
                        break;
                    default:
                        if ('(' === $this->parser->getCurrentToken()->getValue()) {
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
                if ($token->test(Twig_Token::PUNCTUATION_TYPE, '[')) {
                    $node = $this->parseArrayExpression();
                } elseif ($token->test(Twig_Token::PUNCTUATION_TYPE, '{')) {
                    $node = $this->parseHashExpression();
                } else {
                    throw new Twig_Error_Syntax(sprintf('Unexpected token "%s" of value "%s"', Twig_Token::typeToEnglish($token->getType(), $token->getLine()), $token->getValue()), $token->getLine());
                }
        }
        return $this->parsePostfixExpression($node);
    }
    public function parseStringExpression()
    {
        $stream = $this->parser->getStream();
        $nodes = array();
        // a string cannot be followed by another string in a single expression
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
        $stream->expect(Twig_Token::PUNCTUATION_TYPE, '[', 'An array element was expected');
        $node = new Twig_Node_Expression_Array(array(), $stream->getCurrent()->getLine());
        $first = true;
        while (!$stream->test(Twig_Token::PUNCTUATION_TYPE, ']')) {
            if (!$first) {
                $stream->expect(Twig_Token::PUNCTUATION_TYPE, ',', 'An array element must be followed by a comma');
                // trailing ,?
                if ($stream->test(Twig_Token::PUNCTUATION_TYPE, ']')) {
                    break;
                }
            }
            $first = false;
            $node->addElement($this->parseExpression());
        }
        $stream->expect(Twig_Token::PUNCTUATION_TYPE, ']', 'An opened array is not properly closed');
        return $node;
    }
    public function parseHashExpression()
    {
        $stream = $this->parser->getStream();
        $stream->expect(Twig_Token::PUNCTUATION_TYPE, '{', 'A hash element was expected');
        $node = new Twig_Node_Expression_Array(array(), $stream->getCurrent()->getLine());
        $first = true;
        while (!$stream->test(Twig_Token::PUNCTUATION_TYPE, '}')) {
            if (!$first) {
                $stream->expect(Twig_Token::PUNCTUATION_TYPE, ',', 'A hash value must be followed by a comma');
                // trailing ,?
                if ($stream->test(Twig_Token::PUNCTUATION_TYPE, '}')) {
                    break;
                }
            }
            $first = false;
            // a hash key can be:
            //
            //  * a number -- 12
            //  * a string -- 'a'
            //  * a name, which is equivalent to a string -- a
            //  * an expression, which must be enclosed in parentheses -- (1 + 2)
            if ($stream->test(Twig_Token::STRING_TYPE) || $stream->test(Twig_Token::NAME_TYPE) || $stream->test(Twig_Token::NUMBER_TYPE)) {
                $token = $stream->next();
                $key = new Twig_Node_Expression_Constant($token->getValue(), $token->getLine());
            } elseif ($stream->test(Twig_Token::PUNCTUATION_TYPE, '(')) {
                $key = $this->parseExpression();
            } else {
                $current = $stream->getCurrent();
                throw new Twig_Error_Syntax(sprintf('A hash key must be a quoted string, a number, a name, or an expression enclosed in parentheses (unexpected token "%s" of value "%s"', Twig_Token::typeToEnglish($current->getType(), $current->getLine()), $current->getValue()), $current->getLine());
            }
            $stream->expect(Twig_Token::PUNCTUATION_TYPE, ':', 'A hash key must be followed by a colon (:)');
            $value = $this->parseExpression();
            $node->addElement($value, $key);
        }
        $stream->expect(Twig_Token::PUNCTUATION_TYPE, '}', 'An opened hash is not properly closed');
        return $node;
    }
    public function parsePostfixExpression($node)
    {
        while (true) {
            $token = $this->parser->getCurrentToken();
            if ($token->getType() == Twig_Token::PUNCTUATION_TYPE) {
                if ('.' == $token->getValue() || '[' == $token->getValue()) {
                    $node = $this->parseSubscriptExpression($node);
                } elseif ('|' == $token->getValue()) {
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
        $args = $this->parseArguments();
        switch ($name) {
            case 'parent':
                if (!count($this->parser->getBlockStack())) {
                    throw new Twig_Error_Syntax('Calling "parent" outside a block is forbidden', $line);
                }
                if (!$this->parser->getParent() && !$this->parser->hasTraits()) {
                    throw new Twig_Error_Syntax('Calling "parent" on a template that does not extend nor "use" another template is forbidden', $line);
                }
                return new Twig_Node_Expression_Parent($this->parser->peekBlockStack(), $line);
            case 'block':
                return new Twig_Node_Expression_BlockReference($args->getNode(0), false, $line);
            case 'attribute':
                if (count($args) < 2) {
                    throw new Twig_Error_Syntax('The "attribute" function takes at least two arguments (the variable and the attributes)', $line);
                }
                return new Twig_Node_Expression_GetAttr($args->getNode(0), $args->getNode(1), count($args) > 2 ? $args->getNode(2) : new Twig_Node_Expression_Array(array(), $line), Twig_TemplateInterface::ANY_CALL, $line);
            default:
                if (null !== $alias = $this->parser->getImportedFunction($name)) {
                    $arguments = new Twig_Node_Expression_Array(array(), $line);
                    foreach ($args as $n) {
                        $arguments->addElement($n);
                    }
                    $node = new Twig_Node_Expression_MethodCall($alias['node'], $alias['name'], $arguments, $line);
                    $node->setAttribute('safe', true);
                    return $node;
                }
                $class = $this->getFunctionNodeClass($name);
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
        if ($token->getValue() == '.') {
            $token = $stream->next();
            if (
                $token->getType() == Twig_Token::NAME_TYPE
                ||
                $token->getType() == Twig_Token::NUMBER_TYPE
                ||
                ($token->getType() == Twig_Token::OPERATOR_TYPE && preg_match(Twig_Lexer::REGEX_NAME, $token->getValue()))
            ) {
                $arg = new Twig_Node_Expression_Constant($token->getValue(), $lineno);
                if ($stream->test(Twig_Token::PUNCTUATION_TYPE, '(')) {
                    $type = Twig_TemplateInterface::METHOD_CALL;
                    foreach ($this->parseArguments() as $n) {
                        $arguments->addElement($n);
                    }
                }
            } else {
                throw new Twig_Error_Syntax('Expected name or number', $lineno);
            }
        } else {
            $type = Twig_TemplateInterface::ARRAY_CALL;
            $arg = $this->parseExpression();
            // slice?
            if ($stream->test(Twig_Token::PUNCTUATION_TYPE, ':')) {
                $stream->next();
                if ($stream->test(Twig_Token::PUNCTUATION_TYPE, ']')) {
                    $length = new Twig_Node_Expression_Constant(null, $token->getLine());
                } else {
                    $length = $this->parseExpression();
                }
                $class = $this->getFilterNodeClass('slice');
                $arguments = new Twig_Node(array($arg, $length));
                $filter = new $class($node, new Twig_Node_Expression_Constant('slice', $token->getLine()), $arguments, $token->getLine());
                $stream->expect(Twig_Token::PUNCTUATION_TYPE, ']');
                return $filter;
            }
            $stream->expect(Twig_Token::PUNCTUATION_TYPE, ']');
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
            if (!$this->parser->getStream()->test(Twig_Token::PUNCTUATION_TYPE, '(')) {
                $arguments = new Twig_Node();
            } else {
                $arguments = $this->parseArguments();
            }
            $class = $this->getFilterNodeClass($name->getAttribute('value'));
            $node = new $class($node, $name, $arguments, $token->getLine(), $tag);
            if (!$this->parser->getStream()->test(Twig_Token::PUNCTUATION_TYPE, '|')) {
                break;
            }
            $this->parser->getStream()->next();
        }
        return $node;
    }
    public function parseArguments()
    {
        $args = array();
        $stream = $this->parser->getStream();
        $stream->expect(Twig_Token::PUNCTUATION_TYPE, '(', 'A list of arguments must be opened by a parenthesis');
        while (!$stream->test(Twig_Token::PUNCTUATION_TYPE, ')')) {
            if (!empty($args)) {
                $stream->expect(Twig_Token::PUNCTUATION_TYPE, ',', 'Arguments must be separated by a comma');
            }
            $args[] = $this->parseExpression();
        }
        $stream->expect(Twig_Token::PUNCTUATION_TYPE, ')', 'A list of arguments must be closed by a parenthesis');
        return new Twig_Node($args);
    }
    public function parseAssignmentExpression()
    {
        $targets = array();
        while (true) {
            $token = $this->parser->getStream()->expect(Twig_Token::NAME_TYPE, null, 'Only variables can be assigned to');
            if (in_array($token->getValue(), array('true', 'false', 'none'))) {
                throw new Twig_Error_Syntax(sprintf('You cannot assign a value to "%s"', $token->getValue()), $token->getLine());
            }
            $targets[] = new Twig_Node_Expression_AssignName($token->getValue(), $token->getLine());
            if (!$this->parser->getStream()->test(Twig_Token::PUNCTUATION_TYPE, ',')) {
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
            if (!$this->parser->getStream()->test(Twig_Token::PUNCTUATION_TYPE, ',')) {
                break;
            }
            $this->parser->getStream()->next();
        }
        return new Twig_Node($targets);
    }
    protected function getFunctionNodeClass($name)
    {
        $functionMap = $this->parser->getEnvironment()->getFunctions();
        if (isset($functionMap[$name]) && $functionMap[$name] instanceof Twig_Function_Node) {
            return $functionMap[$name]->getClass();
        }
        return 'Twig_Node_Expression_Function';
    }
    protected function getFilterNodeClass($name)
    {
        $filterMap = $this->parser->getEnvironment()->getFilters();
        if (isset($filterMap[$name]) && $filterMap[$name] instanceof Twig_Filter_Node) {
            return $filterMap[$name]->getClass();
        }
        return 'Twig_Node_Expression_Filter';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Interface implemented by extension classes.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
interface Twig_ExtensionInterface
{
    /**
     * Initializes the runtime environment.
     *
     * This is where you can load some file that contains filter functions for instance.
     *
     * @param Twig_Environment $environment The current Twig_Environment instance
     */
    function initRuntime(Twig_Environment $environment);
    /**
     * Returns the token parser instances to add to the existing list.
     *
     * @return array An array of Twig_TokenParserInterface or Twig_TokenParserBrokerInterface instances
     */
    function getTokenParsers();
    /**
     * Returns the node visitor instances to add to the existing list.
     *
     * @return array An array of Twig_NodeVisitorInterface instances
     */
    function getNodeVisitors();
    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return array An array of filters
     */
    function getFilters();
    /**
     * Returns a list of tests to add to the existing list.
     *
     * @return array An array of tests
     */
    function getTests();
    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    function getFunctions();
    /**
     * Returns a list of operators to add to the existing list.
     *
     * @return array An array of operators
     */
    function getOperators();
    /**
     * Returns a list of global variables to add to the existing list.
     *
     * @return array An array of global variables
     */
    function getGlobals();
    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    function getName();
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
abstract class Twig_Extension implements Twig_ExtensionInterface
{
    /**
     * Initializes the runtime environment.
     *
     * This is where you can load some file that contains filter functions for instance.
     *
     * @param Twig_Environment $environment The current Twig_Environment instance
     */
    public function initRuntime(Twig_Environment $environment)
    {
    }
    /**
     * Returns the token parser instances to add to the existing list.
     *
     * @return array An array of Twig_TokenParserInterface or Twig_TokenParserBrokerInterface instances
     */
    public function getTokenParsers()
    {
        return array();
    }
    /**
     * Returns the node visitor instances to add to the existing list.
     *
     * @return array An array of Twig_NodeVisitorInterface instances
     */
    public function getNodeVisitors()
    {
        return array();
    }
    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return array An array of filters
     */
    public function getFilters()
    {
        return array();
    }
    /**
     * Returns a list of tests to add to the existing list.
     *
     * @return array An array of tests
     */
    public function getTests()
    {
        return array();
    }
    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return array();
    }
    /**
     * Returns a list of operators to add to the existing list.
     *
     * @return array An array of operators
     */
    public function getOperators()
    {
        return array();
    }
    /**
     * Returns a list of global variables to add to the existing list.
     *
     * @return array An array of global variables
     */
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
/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Extension_Core extends Twig_Extension
{
    protected $dateFormats = array('F j, Y H:i', '%d days');
    protected $numberFormat = array(0, '.', ',');
    protected $timezone = null;
    /**
     * Sets the default format to be used by the date filter.
     *
     * @param string $format             The default date format string
     * @param string $dateIntervalFormat The default date interval format string
     */
    public function setDateFormat($format = null, $dateIntervalFormat = null)
    {
        if (null !== $format) {
            $this->dateFormats[0] = $format;
        }
        if (null !== $dateIntervalFormat) {
            $this->dateFormats[1] = $dateIntervalFormat;
        }
    }
    /**
     * Gets the default format to be used by the date filter.
     *
     * @return array The default date format string and the default date interval format string
     */
    public function getDateFormat()
    {
        return $this->dateFormats;
    }
    /**
     * Sets the default timezone to be used by the date filter.
     *
     * @param DateTimeZone|string $timezone The default timezone string or a DateTimeZone object
     */
    public function setTimezone($timezone)
    {
        $this->timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
    }
    /**
     * Gets the default timezone to be used by the date filter.
     *
     * @return DateTimeZone The default timezone currently in use
     */
    public function getTimezone()
    {
        return $this->timezone;
    }
    /**
     * Sets the default format to be used by the number_format filter.
     *
     * @param integer $decimal      The number of decimal places to use.
     * @param string  $decimalPoint The character(s) to use for the decimal point.
     * @param string  $thousandSep  The character(s) to use for the thousands separator.
     */
    public function setNumberFormat($decimal, $decimalPoint, $thousandSep)
    {
        $this->numberFormat = array($decimal, $decimalPoint, $thousandSep);
    }
    /**
     * Get the default format used by the number_format filter.
     *
     * @return array The arguments for number_format()
     */
    public function getNumberFormat()
    {
        return $this->numberFormat;
    }
    /**
     * Returns the token parser instance to add to the existing list.
     *
     * @return array An array of Twig_TokenParser instances
     */
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
    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return array An array of filters
     */
    public function getFilters()
    {
        $filters = array(
            // formatting filters
            'date'          => new Twig_Filter_Function('twig_date_format_filter', array('needs_environment' => true)),
            'date_modify'   => new Twig_Filter_Function('twig_date_modify_filter', array('needs_environment' => true)),
            'format'        => new Twig_Filter_Function('sprintf'),
            'replace'       => new Twig_Filter_Function('strtr'),
            'number_format' => new Twig_Filter_Function('twig_number_format_filter', array('needs_environment' => true)),
            'abs'           => new Twig_Filter_Function('abs'),
            // encoding
            'url_encode'       => new Twig_Filter_Function('twig_urlencode_filter'),
            'json_encode'      => new Twig_Filter_Function('twig_jsonencode_filter'),
            'convert_encoding' => new Twig_Filter_Function('twig_convert_encoding'),
            // string filters
            'title'      => new Twig_Filter_Function('twig_title_string_filter', array('needs_environment' => true)),
            'capitalize' => new Twig_Filter_Function('twig_capitalize_string_filter', array('needs_environment' => true)),
            'upper'      => new Twig_Filter_Function('strtoupper'),
            'lower'      => new Twig_Filter_Function('strtolower'),
            'striptags'  => new Twig_Filter_Function('strip_tags'),
            'trim'       => new Twig_Filter_Function('trim'),
            'nl2br'      => new Twig_Filter_Function('nl2br', array('pre_escape' => 'html', 'is_safe' => array('html'))),
            // array helpers
            'join'    => new Twig_Filter_Function('twig_join_filter'),
            'sort'    => new Twig_Filter_Function('twig_sort_filter'),
            'merge'   => new Twig_Filter_Function('twig_array_merge'),
            // string/array filters
            'reverse' => new Twig_Filter_Function('twig_reverse_filter', array('needs_environment' => true)),
            'length'  => new Twig_Filter_Function('twig_length_filter', array('needs_environment' => true)),
            'slice'   => new Twig_Filter_Function('twig_slice', array('needs_environment' => true)),
            // iteration and runtime
            'default' => new Twig_Filter_Node('Twig_Node_Expression_Filter_Default'),
            '_default' => new Twig_Filter_Function('_twig_default_filter'),
            'keys'    => new Twig_Filter_Function('twig_get_array_keys_filter'),
            // escaping
            'escape' => new Twig_Filter_Function('twig_escape_filter', array('needs_environment' => true, 'is_safe_callback' => 'twig_escape_filter_is_safe')),
            'e'      => new Twig_Filter_Function('twig_escape_filter', array('needs_environment' => true, 'is_safe_callback' => 'twig_escape_filter_is_safe')),
        );
        if (function_exists('mb_get_info')) {
            $filters['upper'] = new Twig_Filter_Function('twig_upper_filter', array('needs_environment' => true));
            $filters['lower'] = new Twig_Filter_Function('twig_lower_filter', array('needs_environment' => true));
        }
        return $filters;
    }
    /**
     * Returns a list of global functions to add to the existing list.
     *
     * @return array An array of global functions
     */
    public function getFunctions()
    {
        return array(
            'range'    => new Twig_Function_Function('range'),
            'constant' => new Twig_Function_Function('constant'),
            'cycle'    => new Twig_Function_Function('twig_cycle'),
            'random'   => new Twig_Function_Function('twig_random', array('needs_environment' => true)),
            'date'     => new Twig_Function_Function('twig_date_converter', array('needs_environment' => true)),
        );
    }
    /**
     * Returns a list of tests to add to the existing list.
     *
     * @return array An array of tests
     */
    public function getTests()
    {
        return array(
            'even'        => new Twig_Test_Node('Twig_Node_Expression_Test_Even'),
            'odd'         => new Twig_Test_Node('Twig_Node_Expression_Test_Odd'),
            'defined'     => new Twig_Test_Node('Twig_Node_Expression_Test_Defined'),
            'sameas'      => new Twig_Test_Node('Twig_Node_Expression_Test_Sameas'),
            'none'        => new Twig_Test_Node('Twig_Node_Expression_Test_Null'),
            'null'        => new Twig_Test_Node('Twig_Node_Expression_Test_Null'),
            'divisibleby' => new Twig_Test_Node('Twig_Node_Expression_Test_Divisibleby'),
            'constant'    => new Twig_Test_Node('Twig_Node_Expression_Test_Constant'),
            'empty'       => new Twig_Test_Function('twig_test_empty'),
            'iterable'    => new Twig_Test_Function('twig_test_iterable'),
        );
    }
    /**
     * Returns a list of operators to add to the existing list.
     *
     * @return array An array of operators
     */
    public function getOperators()
    {
        return array(
            array(
                'not' => array('precedence' => 50, 'class' => 'Twig_Node_Expression_Unary_Not'),
                '-'   => array('precedence' => 500, 'class' => 'Twig_Node_Expression_Unary_Neg'),
                '+'   => array('precedence' => 500, 'class' => 'Twig_Node_Expression_Unary_Pos'),
            ),
            array(
                'b-and'  => array('precedence' => 5, 'class' => 'Twig_Node_Expression_Binary_BitwiseAnd', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'b-xor'  => array('precedence' => 5, 'class' => 'Twig_Node_Expression_Binary_BitwiseXor', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'b-or'   => array('precedence' => 5, 'class' => 'Twig_Node_Expression_Binary_BitwiseOr', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'or'     => array('precedence' => 10, 'class' => 'Twig_Node_Expression_Binary_Or', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'and'    => array('precedence' => 15, 'class' => 'Twig_Node_Expression_Binary_And', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '=='     => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_Equal', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '!='     => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_NotEqual', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '<'      => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_Less', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '>'      => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_Greater', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '>='     => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_GreaterEqual', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '<='     => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_LessEqual', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'not in' => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_NotIn', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'in'     => array('precedence' => 20, 'class' => 'Twig_Node_Expression_Binary_In', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '..'     => array('precedence' => 25, 'class' => 'Twig_Node_Expression_Binary_Range', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '+'      => array('precedence' => 30, 'class' => 'Twig_Node_Expression_Binary_Add', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '-'      => array('precedence' => 30, 'class' => 'Twig_Node_Expression_Binary_Sub', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '~'      => array('precedence' => 40, 'class' => 'Twig_Node_Expression_Binary_Concat', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '*'      => array('precedence' => 60, 'class' => 'Twig_Node_Expression_Binary_Mul', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '/'      => array('precedence' => 60, 'class' => 'Twig_Node_Expression_Binary_Div', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '//'     => array('precedence' => 60, 'class' => 'Twig_Node_Expression_Binary_FloorDiv', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '%'      => array('precedence' => 60, 'class' => 'Twig_Node_Expression_Binary_Mod', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'is'     => array('precedence' => 100, 'callable' => array($this, 'parseTestExpression'), 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'is not' => array('precedence' => 100, 'callable' => array($this, 'parseNotTestExpression'), 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                '**'     => array('precedence' => 200, 'class' => 'Twig_Node_Expression_Binary_Power', 'associativity' => Twig_ExpressionParser::OPERATOR_RIGHT),
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
        if ($stream->test(Twig_Token::PUNCTUATION_TYPE, '(')) {
            $arguments = $parser->getExpressionParser()->parseArguments();
        }
        $class = $this->getTestNodeClass($parser->getEnvironment(), $name);
        return new $class($node, $name, $arguments, $parser->getCurrentToken()->getLine());
    }
    protected function getTestNodeClass(Twig_Environment $env, $name)
    {
        $testMap = $env->getTests();
        if (isset($testMap[$name]) && $testMap[$name] instanceof Twig_Test_Node) {
            return $testMap[$name]->getClass();
        }
        return 'Twig_Node_Expression_Test';
    }
    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'core';
    }
}
/**
 * Cycles over a value.
 *
 * @param ArrayAccess|array $values An array or an ArrayAccess instance
 * @param integer           $i      The cycle value
 *
 * @return string The next value in the cycle
 */
function twig_cycle($values, $i)
{
    if (!is_array($values) && !$values instanceof ArrayAccess) {
        return $values;
    }
    return $values[$i % count($values)];
}
/**
 * Returns a random value depending on the supplied parameter type:
 * - a random item from a Traversable or array
 * - a random character from a string
 * - a random integer between 0 and the integer parameter
 *
 * @param Twig_Environment                 $env    A Twig_Environment instance
 * @param Traversable|array|integer|string $values The values to pick a random item from
 *
 * @throws Twig_Error_Runtime When $values is an empty array (does not apply to an empty string which is returned as is).
 *
 * @return mixed A random value from the given sequence
 */
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
        if ('' === $values) {
            return '';
        }
        if (null !== $charset = $env->getCharset()) {
            if ('UTF-8' != $charset) {
                $values = twig_convert_encoding($values, 'UTF-8', $charset);
            }
            // unicode version of str_split()
            // split at all positions, but not after the start and not before the end
            $values = preg_split('/(?<!^)(?!$)/u', $values);
            if ('UTF-8' != $charset) {
                foreach ($values as $i => $value) {
                    $values[$i] = twig_convert_encoding($value, $charset, 'UTF-8');
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
/**
 * Converts a date to the given format.
 *
 * <pre>
 *   {{ post.published_at|date("m/d/Y") }}
 * </pre>
 *
 * @param Twig_Environment             $env      A Twig_Environment instance
 * @param DateTime|DateInterval|string $date     A date
 * @param string                       $format   A format
 * @param DateTimeZone|string          $timezone A timezone
 *
 * @return string The formatted date
 */
function twig_date_format_filter(Twig_Environment $env, $date, $format = null, $timezone = null)
{
    if (null === $format) {
        $formats = $env->getExtension('core')->getDateFormat();
        $format = $date instanceof DateInterval ? $formats[1] : $formats[0];
    }
    if ($date instanceof DateInterval || $date instanceof DateTime) {
        if (null !== $timezone) {
            $date = clone $date;
            $date->setTimezone($timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone));
        }
        return $date->format($format);
    }
    return twig_date_converter($env, $date, $timezone)->format($format);
}
/**
 * Returns a new date object modified
 *
 * <pre>
 *   {{ post.published_at|modify("-1day")|date("m/d/Y") }}
 * </pre>
 *
 * @param Twig_Environment  $env      A Twig_Environment instance
 * @param DateTime|string   $date     A date
 * @param string            $modifier A modifier string
 *
 * @return DateTime A new date object
 */
function twig_date_modify_filter(Twig_Environment $env, $date, $modifier)
{
    if ($date instanceof DateTime) {
        $date = clone $date;
    } else {
        $date = twig_date_converter($env, $date);
    }
    $date->modify($modifier);
    return $date;
}
/**
 * Converts an input to a DateTime instance.
 *
 * <pre>
 *    {% if date(user.created_at) < date('+2days') %}
 *      {# do something #}
 *    {% endif %}
 * </pre>
 *
 * @param Twig_Environment    $env      A Twig_Environment instance
 * @param DateTime|string     $date     A date
 * @param DateTimeZone|string $timezone A timezone
 *
 * @return DateTime A DateTime instance
 */
function twig_date_converter(Twig_Environment $env, $date = null, $timezone = null)
{
    if (!$date instanceof DateTime) {
        $asString = (string) $date;
        if (ctype_digit($asString) || (!empty($asString) && '-' === $asString[0] && ctype_digit(substr($asString, 1)))) {
            $date = new DateTime('@'.$date);
        } else {
            $date = new DateTime($date);
        }
    } else {
        $date = clone $date;
    }
    // set Timezone
    if (null !== $timezone) {
        if ($timezone instanceof DateTimeZone) {
            $date->setTimezone($timezone);
        } else {
            $date->setTimezone(new DateTimeZone($timezone));
        }
    } elseif (($timezone = $env->getExtension('core')->getTimezone()) instanceof DateTimeZone) {
        $date->setTimezone($timezone);
    } else {
        $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }
    return $date;
}
/**
 * Number format filter.
 *
 * All of the formatting options can be left null, in that case the defaults will
 * be used.  Supplying any of the parameters will override the defaults set in the
 * environment object.
 *
 * @param Twig_Environment    $env          A Twig_Environment instance
 * @param mixed               $number       A float/int/string of the number to format
 * @param integer             $decimal      The number of decimal points to display.
 * @param string              $decimalPoint The character(s) to use for the decimal point.
 * @param string              $thousandSep  The character(s) to use for the thousands separator.
 *
 * @return string The formatted number
 */
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
/**
 * URL encodes a string.
 *
 * @param string $url A URL
 * @param bool   $raw true to use rawurlencode() instead of urlencode
 *
 * @return string The URL encoded value
 */
function twig_urlencode_filter($url, $raw = false)
{
    if ($raw) {
        return rawurlencode($url);
    }
    return urlencode($url);
}
if (version_compare(PHP_VERSION, '5.3.0', '<')) {
    /**
     * JSON encodes a variable.
     *
     * @param mixed   $value   The value to encode.
     * @param integer $options Not used on PHP 5.2.x
     *
     * @return mixed The JSON encoded value
     */
    function twig_jsonencode_filter($value, $options = 0)
    {
        if ($value instanceof Twig_Markup) {
            $value = (string) $value;
        } elseif (is_array($value)) {
            array_walk_recursive($value, '_twig_markup2string');
        }
        return json_encode($value);
    }
} else {
    /**
     * JSON encodes a variable.
     *
     * @param mixed   $value   The value to encode.
     * @param integer $options Bitmask consisting of JSON_HEX_QUOT, JSON_HEX_TAG, JSON_HEX_AMP, JSON_HEX_APOS, JSON_NUMERIC_CHECK, JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES, JSON_FORCE_OBJECT
     *
     * @return mixed The JSON encoded value
     */
    function twig_jsonencode_filter($value, $options = 0)
    {
        if ($value instanceof Twig_Markup) {
            $value = (string) $value;
        } elseif (is_array($value)) {
            array_walk_recursive($value, '_twig_markup2string');
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
/**
 * Merges an array with another one.
 *
 * <pre>
 *  {% set items = { 'apple': 'fruit', 'orange': 'fruit' } %}
 *
 *  {% set items = items|merge({ 'peugeot': 'car' }) %}
 *
 *  {# items now contains { 'apple': 'fruit', 'orange': 'fruit', 'peugeot': 'car' } #}
 * </pre>
 *
 * @param array $arr1 An array
 * @param array $arr2 An array
 *
 * @return array The merged array
 */
function twig_array_merge($arr1, $arr2)
{
    if (!is_array($arr1) || !is_array($arr2)) {
        throw new Twig_Error_Runtime('The merge filter only works with arrays or hashes.');
    }
    return array_merge($arr1, $arr2);
}
/**
 * Slices a variable.
 *
 * @param Twig_Environment $env          A Twig_Environment instance
 * @param mixed            $item         A variable
 * @param integer          $start        Start of the slice
 * @param integer          $length       Size of the slice
 * @param Boolean          $preserveKeys Whether to preserve key or not (when the input is an array)
 *
 * @return mixed The sliced variable
 */
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
/**
 * Joins the values to a string.
 *
 * The separator between elements is an empty string per default, you can define it with the optional parameter.
 *
 * <pre>
 *  {{ [1, 2, 3]|join('|') }}
 *  {# returns 1|2|3 #}
 *
 *  {{ [1, 2, 3]|join }}
 *  {# returns 123 #}
 * </pre>
 *
 * @param array  $value An array
 * @param string $glue  The separator
 *
 * @return string The concatenated string
 */
function twig_join_filter($value, $glue = '')
{
    if ($value instanceof Traversable) {
        $value = iterator_to_array($value, false);
    }
    return implode($glue, (array) $value);
}
// The '_default' filter is used internally to avoid using the ternary operator
// which costs a lot for big contexts (before PHP 5.4). So, on average,
// a function call is cheaper.
function _twig_default_filter($value, $default = '')
{
    if (twig_test_empty($value)) {
        return $default;
    }
    return $value;
}
/**
 * Returns the keys for the given array.
 *
 * It is useful when you want to iterate over the keys of an array:
 *
 * <pre>
 *  {% for key in array|keys %}
 *      {# ... #}
 *  {% endfor %}
 * </pre>
 *
 * @param array $array An array
 *
 * @return array The keys
 */
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
/**
 * Reverses a variable.
 *
 * @param Twig_Environment         $env          A Twig_Environment instance
 * @param array|Traversable|string $item         An array, a Traversable instance, or a string
 * @param Boolean                  $preserveKeys Whether to preserve key or not
 *
 * @return mixed The reversed input
 */
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
        if ('UTF-8' != $charset) {
            $item = twig_convert_encoding($string, 'UTF-8', $charset);
        }
        preg_match_all('/./us', $item, $matches);
        $string = implode('', array_reverse($matches[0]));
        if ('UTF-8' != $charset) {
            $string = twig_convert_encoding($string, $charset, 'UTF-8');
        }
        return $string;
    }
    return strrev((string) $item);
}
/**
 * Sorts an array.
 *
 * @param array $array An array
 */
function twig_sort_filter($array)
{
    asort($array);
    return $array;
}
/* used internally */
function twig_in_filter($value, $compare)
{
    $strict = is_object($value);
    if (is_array($compare)) {
        return in_array($value, $compare, $strict);
    } elseif (is_string($compare)) {
        if (!strlen((string) $value)) {
            return empty($compare);
        }
        return false !== strpos($compare, (string) $value);
    } elseif (is_object($compare) && $compare instanceof Traversable) {
        return in_array($value, iterator_to_array($compare, false), $strict);
    }
    return false;
}
/**
 * Escapes a string.
 *
 * @param Twig_Environment $env        A Twig_Environment instance
 * @param string           $string     The value to be escaped
 * @param string           $strategy   The escaping strategy
 * @param string           $charset    The charset
 * @param Boolean          $autoescape Whether the function is called by the auto-escaping feature (true) or by the developer (false)
 */
function twig_escape_filter(Twig_Environment $env, $string, $strategy = 'html', $charset = null, $autoescape = false)
{
    if ($autoescape && is_object($string) && $string instanceof Twig_Markup) {
        return $string;
    }
    if (!is_string($string) && !(is_object($string) && method_exists($string, '__toString'))) {
        return $string;
    }
    if (null === $charset) {
        $charset = $env->getCharset();
    }
    $string = (string) $string;
    switch ($strategy) {
        case 'js':
            // escape all non-alphanumeric characters
            // into their \xHH or \uHHHH representations
            if ('UTF-8' != $charset) {
                $string = twig_convert_encoding($string, 'UTF-8', $charset);
            }
            if (0 == strlen($string) ? false : (1 == preg_match('/^./su', $string) ? false : true)) {
                throw new Twig_Error_Runtime('The string to escape is not a valid UTF-8 string.');
            }
            $string = preg_replace_callback('#[^a-zA-Z0-9,\._]#Su', '_twig_escape_js_callback', $string);
            if ('UTF-8' != $charset) {
                $string = twig_convert_encoding($string, $charset, 'UTF-8');
            }
            return $string;
        case 'css':
            if ('UTF-8' != $charset) {
                $string = twig_convert_encoding($string, 'UTF-8', $charset);
            }
            if (0 == strlen($string) ? false : (1 == preg_match('/^./su', $string) ? false : true)) {
                throw new Twig_Error_Runtime('The string to escape is not a valid UTF-8 string.');
            }
            $string = preg_replace_callback('#[^a-zA-Z0-9]#Su', '_twig_escape_css_callback', $string);
            if ('UTF-8' != $charset) {
                $string = twig_convert_encoding($string, $charset, 'UTF-8');
            }
            return $string;
        case 'html_attr':
            if ('UTF-8' != $charset) {
                $string = twig_convert_encoding($string, 'UTF-8', $charset);
            }
            if (0 == strlen($string) ? false : (1 == preg_match('/^./su', $string) ? false : true)) {
                throw new Twig_Error_Runtime('The string to escape is not a valid UTF-8 string.');
            }
            $string = preg_replace_callback('#[^a-zA-Z0-9,\.\-_]#Su', '_twig_escape_html_attr_callback', $string);
            if ('UTF-8' != $charset) {
                $string = twig_convert_encoding($string, $charset, 'UTF-8');
            }
            return $string;
        case 'html':
            // see http://php.net/htmlspecialchars
            // Using a static variable to avoid initializing the array
            // each time the function is called. Moving the declaration on the
            // top of the function slow downs other escaping strategies.
            static $htmlspecialcharsCharsets = array(
                'iso-8859-1' => true, 'iso8859-1' => true,
                'iso-8859-15' => true, 'iso8859-15' => true,
                'utf-8' => true,
                'cp866' => true, 'ibm866' => true, '866' => true,
                'cp1251' => true, 'windows-1251' => true, 'win-1251' => true,
                '1251' => true,
                'cp1252' => true, 'windows-1252' => true, '1252' => true,
                'koi8-r' => true, 'koi8-ru' => true, 'koi8r' => true,
                'big5' => true, '950' => true,
                'gb2312' => true, '936' => true,
                'big5-hkscs' => true,
                'shift_jis' => true, 'sjis' => true, '932' => true,
                'euc-jp' => true, 'eucjp' => true,
                'iso8859-5' => true, 'iso-8859-5' => true, 'macroman' => true,
            );
            if (isset($htmlspecialcharsCharsets[strtolower($charset)])) {
                return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, $charset);
            }
            $string = twig_convert_encoding($string, 'UTF-8', $charset);
            $string = htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return twig_convert_encoding($string, $charset, 'UTF-8');
        case 'url':
            if (version_compare(PHP_VERSION, '5.3.0', '<')) {
                return str_replace('%7E', '~', rawurlencode($string));
            }
            return rawurlencode($string);
        default:
            throw new Twig_Error_Runtime(sprintf('Invalid escaping strategy "%s" (valid ones: html, js, url, css, and html_attr).', $strategy));
    }
}
/* used internally */
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
    // \xHH
    if (!isset($char[1])) {
        return '\\x'.strtoupper(substr('00'.bin2hex($char), -2));
    }
    // \uHHHH
    $char = twig_convert_encoding($char, 'UTF-16BE', 'UTF-8');
    return '\\u'.strtoupper(substr('0000'.bin2hex($char), -4));
}
function _twig_escape_css_callback($matches)
{
    $char = $matches[0];
    // \xHH
    if (!isset($char[1])) {
        $hex = ltrim(strtoupper(bin2hex($char)), '0');
        if (0 === strlen($hex)) {
            $hex = '0';
        }
        return '\\'.$hex.' ';
    }
    // \uHHHH
    $char = twig_convert_encoding($char, 'UTF-16BE', 'UTF-8');
    return '\\'.ltrim(strtoupper(bin2hex($char)), '0').' ';
}
/**
 * This function is adapted from code coming from Zend Framework.
 *
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */
function _twig_escape_html_attr_callback($matches)
{
    /*
     * While HTML supports far more named entities, the lowest common denominator
     * has become HTML5's XML Serialisation which is restricted to the those named
     * entities that XML supports. Using HTML entities would result in this error:
     *     XML Parsing Error: undefined entity
     */
    static $entityMap = array(
        34 => 'quot', /* quotation mark */
        38 => 'amp',  /* ampersand */
        60 => 'lt',   /* less-than sign */
        62 => 'gt',   /* greater-than sign */
    );
    $chr = $matches[0];
    $ord = ord($chr);
    /**
     * The following replaces characters undefined in HTML with the
     * hex entity for the Unicode replacement character.
     */
    if (($ord <= 0x1f && $chr != "\t" && $chr != "\n" && $chr != "\r") || ($ord >= 0x7f && $ord <= 0x9f)) {
        return '&#xFFFD;';
    }
    /**
     * Check if the current character to escape has a name entity we should
     * replace it with while grabbing the hex value of the character.
     */
    if (strlen($chr) == 1) {
        $hex = strtoupper(substr('00'.bin2hex($chr), -2));
    } else {
        $chr = twig_convert_encoding($chr, 'UTF-16BE', 'UTF-8');
        $hex = strtoupper(substr('0000'.bin2hex($chr), -4));
    }
    $int = hexdec($hex);
    if (array_key_exists($int, $entityMap)) {
        return sprintf('&%s;', $entityMap[$int]);
    }
    /**
     * Per OWASP recommendations, we'll use hex entities for any other
     * characters where a named entity does not exist.
     */
    return sprintf('&#x%s;', $hex);
}
// add multibyte extensions if possible
if (function_exists('mb_get_info')) {
    /**
     * Returns the length of a variable.
     *
     * @param Twig_Environment $env   A Twig_Environment instance
     * @param mixed            $thing A variable
     *
     * @return integer The length of the value
     */
    function twig_length_filter(Twig_Environment $env, $thing)
    {
        return is_scalar($thing) ? mb_strlen($thing, $env->getCharset()) : count($thing);
    }
    /**
     * Converts a string to uppercase.
     *
     * @param Twig_Environment $env    A Twig_Environment instance
     * @param string           $string A string
     *
     * @return string The uppercased string
     */
    function twig_upper_filter(Twig_Environment $env, $string)
    {
        if (null !== ($charset = $env->getCharset())) {
            return mb_strtoupper($string, $charset);
        }
        return strtoupper($string);
    }
    /**
     * Converts a string to lowercase.
     *
     * @param Twig_Environment $env    A Twig_Environment instance
     * @param string           $string A string
     *
     * @return string The lowercased string
     */
    function twig_lower_filter(Twig_Environment $env, $string)
    {
        if (null !== ($charset = $env->getCharset())) {
            return mb_strtolower($string, $charset);
        }
        return strtolower($string);
    }
    /**
     * Returns a titlecased string.
     *
     * @param Twig_Environment $env    A Twig_Environment instance
     * @param string           $string A string
     *
     * @return string The titlecased string
     */
    function twig_title_string_filter(Twig_Environment $env, $string)
    {
        if (null !== ($charset = $env->getCharset())) {
            return mb_convert_case($string, MB_CASE_TITLE, $charset);
        }
        return ucwords(strtolower($string));
    }
    /**
     * Returns a capitalized string.
     *
     * @param Twig_Environment $env    A Twig_Environment instance
     * @param string           $string A string
     *
     * @return string The capitalized string
     */
    function twig_capitalize_string_filter(Twig_Environment $env, $string)
    {
        if (null !== ($charset = $env->getCharset())) {
            return mb_strtoupper(mb_substr($string, 0, 1, $charset), $charset).
                         mb_strtolower(mb_substr($string, 1, mb_strlen($string, $charset), $charset), $charset);
        }
        return ucfirst(strtolower($string));
    }
}
// and byte fallback
else
{
    /**
     * Returns the length of a variable.
     *
     * @param Twig_Environment $env   A Twig_Environment instance
     * @param mixed            $thing A variable
     *
     * @return integer The length of the value
     */
    function twig_length_filter(Twig_Environment $env, $thing)
    {
        return is_scalar($thing) ? strlen($thing) : count($thing);
    }
    /**
     * Returns a titlecased string.
     *
     * @param Twig_Environment $env    A Twig_Environment instance
     * @param string           $string A string
     *
     * @return string The titlecased string
     */
    function twig_title_string_filter(Twig_Environment $env, $string)
    {
        return ucwords(strtolower($string));
    }
    /**
     * Returns a capitalized string.
     *
     * @param Twig_Environment $env    A Twig_Environment instance
     * @param string           $string A string
     *
     * @return string The capitalized string
     */
    function twig_capitalize_string_filter(Twig_Environment $env, $string)
    {
        return ucfirst(strtolower($string));
    }
}
/* used internally */
function twig_ensure_traversable($seq)
{
    if ($seq instanceof Traversable || is_array($seq)) {
        return $seq;
    }
    return array();
}
/**
 * Checks if a variable is empty.
 *
 * <pre>
 * {# evaluates to true if the foo variable is null, false, or the empty string #}
 * {% if foo is empty %}
 *     {# ... #}
 * {% endif %}
 * </pre>
 *
 * @param mixed $value A variable
 *
 * @return Boolean true if the value is empty, false otherwise
 */
function twig_test_empty($value)
{
    if ($value instanceof Countable) {
        return 0 == count($value);
    }
    return false === $value || (empty($value) && '0' != $value);
}
/**
 * Checks if a variable is traversable.
 *
 * <pre>
 * {# evaluates to true if the foo variable is an array or a traversable object #}
 * {% if foo is traversable %}
 *     {# ... #}
 * {% endif %}
 * </pre>
 *
 * @param mixed $value A variable
 *
 * @return Boolean true if the value is traversable
 */
function twig_test_iterable($value)
{
    return $value instanceof Traversable || is_array($value);
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Extension_Debug extends Twig_Extension
{
    /**
     * Returns a list of global functions to add to the existing list.
     *
     * @return array An array of global functions
     */
    public function getFunctions()
    {
        // dump is safe if var_dump is overridden by xdebug
        $isDumpOutputHtmlSafe = extension_loaded('xdebug')
            // false means that it was not set (and the default is on) or it explicitly enabled
            && (false === ini_get('xdebug.overload_var_dump') || ini_get('xdebug.overload_var_dump'))
            // false means that it was not set (and the default is on) or it explicitly enabled
            // xdebug.overload_var_dump produces HTML only when html_errors is also enabled
            && (false === ini_get('html_errors') || ini_get('html_errors'))
        ;
        return array(
            'dump' => new Twig_Function_Function('twig_var_dump', array('is_safe' => $isDumpOutputHtmlSafe ? array('html') : array(), 'needs_context' => true, 'needs_environment' => true)),
        );
    }
    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'debug';
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Extension_Escaper extends Twig_Extension
{
    protected $defaultStrategy;
    public function __construct($defaultStrategy = 'html')
    {
        $this->setDefaultStrategy($defaultStrategy);
    }
    /**
     * Returns the token parser instances to add to the existing list.
     *
     * @return array An array of Twig_TokenParserInterface or Twig_TokenParserBrokerInterface instances
     */
    public function getTokenParsers()
    {
        return array(new Twig_TokenParser_AutoEscape());
    }
    /**
     * Returns the node visitor instances to add to the existing list.
     *
     * @return array An array of Twig_NodeVisitorInterface instances
     */
    public function getNodeVisitors()
    {
        return array(new Twig_NodeVisitor_Escaper());
    }
    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return array An array of filters
     */
    public function getFilters()
    {
        return array(
            'raw' => new Twig_Filter_Function('twig_raw_filter', array('is_safe' => array('all'))),
        );
    }
    /**
     * Sets the default strategy to use when not defined by the user.
     *
     * The strategy can be a valid PHP callback that takes the template
     * "filename" as an argument and returns the strategy to use.
     *
     * @param mixed $defaultStrategy An escaping strategy
     */
    public function setDefaultStrategy($defaultStrategy)
    {
        // for BC
        if (true === $defaultStrategy) {
            $defaultStrategy = 'html';
        }
        $this->defaultStrategy = $defaultStrategy;
    }
    /**
     * Gets the default strategy to use when not defined by the user.
     *
     * @param string $filename The template "filename"
     *
     * @return string The default strategy to use for the template
     */
    public function getDefaultStrategy($filename)
    {
        // disable string callables to avoid calling a function named html or js,
        // or any other upcoming escaping strategy
        if (!is_string($this->defaultStrategy) && is_callable($this->defaultStrategy)) {
            return call_user_func($this->defaultStrategy, $filename);
        }
        return $this->defaultStrategy;
    }
    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'escaper';
    }
}
/**
 * Marks a variable as being safe.
 *
 * @param string $string A PHP variable
 */
function twig_raw_filter($string)
{
    return $string;
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Extension_Optimizer extends Twig_Extension
{
    protected $optimizers;
    public function __construct($optimizers = -1)
    {
        $this->optimizers = $optimizers;
    }
    /**
     * {@inheritdoc}
     */
    public function getNodeVisitors()
    {
        return array(new Twig_NodeVisitor_Optimizer($this->optimizers));
    }
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'optimizer';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Extension_Sandbox extends Twig_Extension
{
    protected $sandboxedGlobally;
    protected $sandboxed;
    protected $policy;
    public function __construct(Twig_Sandbox_SecurityPolicyInterface $policy, $sandboxed = false)
    {
        $this->policy            = $policy;
        $this->sandboxedGlobally = $sandboxed;
    }
    /**
     * Returns the token parser instances to add to the existing list.
     *
     * @return array An array of Twig_TokenParserInterface or Twig_TokenParserBrokerInterface instances
     */
    public function getTokenParsers()
    {
        return array(new Twig_TokenParser_Sandbox());
    }
    /**
     * Returns the node visitor instances to add to the existing list.
     *
     * @return array An array of Twig_NodeVisitorInterface instances
     */
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
            $this->policy->checkMethodAllowed($obj, '__toString');
        }
        return $obj;
    }
    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'sandbox';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a template filter.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
interface Twig_FilterInterface
{
    /**
     * Compiles a filter.
     *
     * @return string The PHP code for the filter
     */
    function compile();
    function needsEnvironment();
    function needsContext();
    function getSafe(Twig_Node $filterArgs);
    function getPreservesSafety();
    function getPreEscape();
    function setArguments($arguments);
    function getArguments();
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a template filter.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
abstract class Twig_Filter implements Twig_FilterInterface
{
    protected $options;
    protected $arguments = array();
    public function __construct(array $options = array())
    {
        $this->options = array_merge(array(
            'needs_environment' => false,
            'needs_context'     => false,
            'pre_escape'        => null,
            'preserves_safety'  => null,
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
        return null;
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a function template filter.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Filter_Function extends Twig_Filter
{
    protected $function;
    public function __construct($function, array $options = array())
    {
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a method template filter.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Filter_Method extends Twig_Filter
{
    protected $extension;
    protected $method;
    public function __construct(Twig_ExtensionInterface $extension, $method, array $options = array())
    {
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

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a template filter as a node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 * (c) 2010 Arnaud Le Blanc
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a template function.
 *
 * @package    twig
 * @author     Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
interface Twig_FunctionInterface
{
    /**
     * Compiles a function.
     *
     * @return string The PHP code for the function
     */
    function compile();
    function needsEnvironment();
    function needsContext();
    function getSafe(Twig_Node $filterArgs);
    function setArguments($arguments);
    function getArguments();
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a template function.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
abstract class Twig_Function implements Twig_FunctionInterface
{
    protected $options;
    protected $arguments = array();
    public function __construct(array $options = array())
    {
        $this->options = array_merge(array(
            'needs_environment' => false,
            'needs_context'     => false,
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
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2010 Arnaud Le Blanc
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a function template function.
 *
 * @package    twig
 * @author     Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class Twig_Function_Function extends Twig_Function
{
    protected $function;
    public function __construct($function, array $options = array())
    {
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2010 Arnaud Le Blanc
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a method template function.
 *
 * @package    twig
 * @author     Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class Twig_Function_Method extends Twig_Function
{
    protected $extension;
    protected $method;
    public function __construct(Twig_ExtensionInterface $extension, $method, array $options = array())
    {
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

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a template function as a node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Interface implemented by lexer classes.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
interface Twig_LexerInterface
{
    /**
     * Tokenizes a source code.
     *
     * @param string $code     The source code
     * @param string $filename A unique identifier for the source code
     *
     * @return Twig_TokenStream A token stream instance
     */
    function tokenize($code, $filename = null);
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Lexes a template string.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
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
    const STATE_DATA            = 0;
    const STATE_BLOCK           = 1;
    const STATE_VAR             = 2;
    const STATE_STRING          = 3;
    const STATE_INTERPOLATION   = 4;
    const REGEX_NAME            = '/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/A';
    const REGEX_NUMBER          = '/[0-9]+(?:\.[0-9]+)?/A';
    const REGEX_STRING          = '/"([^#"\\\\]*(?:\\\\.[^#"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\'/As';
    const REGEX_DQ_STRING_DELIM = '/"/A';
    const REGEX_DQ_STRING_PART  = '/[^#"\\\\]*(?:(?:\\\\.|#(?!\{))[^#"\\\\]*)*/As';
    const PUNCTUATION           = '()[]{}?:.,|';
    public function __construct(Twig_Environment $env, array $options = array())
    {
        $this->env = $env;
        $this->options = array_merge(array(
            'tag_comment'     => array('{#', '#}'),
            'tag_block'       => array('{%', '%}'),
            'tag_variable'    => array('{{', '}}'),
            'whitespace_trim' => '-',
            'interpolation'   => array('#{', '}'),
        ), $options);
        $this->regexes = array(
            'lex_var'             => '/\s*'.preg_quote($this->options['whitespace_trim'].$this->options['tag_variable'][1], '/').'\s*|\s*'.preg_quote($this->options['tag_variable'][1], '/').'/A',
            'lex_block'           => '/\s*(?:'.preg_quote($this->options['whitespace_trim'].$this->options['tag_block'][1], '/').'\s*|\s*'.preg_quote($this->options['tag_block'][1], '/').')\n?/A',
            'lex_raw_data'        => '/('.preg_quote($this->options['tag_block'][0].$this->options['whitespace_trim'], '/').'|'.preg_quote($this->options['tag_block'][0], '/').')\s*endraw\s*(?:'.preg_quote($this->options['whitespace_trim'].$this->options['tag_block'][1], '/').'\s*|\s*'.preg_quote($this->options['tag_block'][1], '/').')/s',
            'operator'            => $this->getOperatorRegex(),
            'lex_comment'         => '/(?:'.preg_quote($this->options['whitespace_trim'], '/').preg_quote($this->options['tag_comment'][1], '/').'\s*|'.preg_quote($this->options['tag_comment'][1], '/').')\n?/s',
            'lex_block_raw'       => '/\s*raw\s*(?:'.preg_quote($this->options['whitespace_trim'].$this->options['tag_block'][1], '/').'\s*|\s*'.preg_quote($this->options['tag_block'][1], '/').')/As',
            'lex_block_line'      => '/\s*line\s+(\d+)\s*'.preg_quote($this->options['tag_block'][1], '/').'/As',
            'lex_tokens_start'    => '/('.preg_quote($this->options['tag_variable'][0], '/').'|'.preg_quote($this->options['tag_block'][0], '/').'|'.preg_quote($this->options['tag_comment'][0], '/').')('.preg_quote($this->options['whitespace_trim'], '/').')?/s',
            'interpolation_start' => '/'.preg_quote($this->options['interpolation'][0], '/').'\s*/A',
            'interpolation_end'   => '/\s*'.preg_quote($this->options['interpolation'][1], '/').'/A',
        );
    }
    /**
     * Tokenizes a source code.
     *
     * @param string $code     The source code
     * @param string $filename A unique identifier for the source code
     *
     * @return Twig_TokenStream A token stream instance
     */
    public function tokenize($code, $filename = null)
    {
        if (function_exists('mb_internal_encoding') && ((int) ini_get('mbstring.func_overload')) & 2) {
            $mbEncoding = mb_internal_encoding();
            mb_internal_encoding('ASCII');
        }
        $this->code = str_replace(array("\r\n", "\r"), "\n", $code);
        $this->filename = $filename;
        $this->cursor = 0;
        $this->lineno = 1;
        $this->end = strlen($this->code);
        $this->tokens = array();
        $this->state = self::STATE_DATA;
        $this->states = array();
        $this->brackets = array();
        $this->position = -1;
        // find all token starts in one go
        preg_match_all($this->regexes['lex_tokens_start'], $this->code, $matches, PREG_OFFSET_CAPTURE);
        $this->positions = $matches;
        while ($this->cursor < $this->end) {
            // dispatch to the lexing functions depending
            // on the current state
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
        // if no matches are left we return the rest of the template as simple text token
        if ($this->position == count($this->positions[0]) - 1) {
            $this->pushToken(Twig_Token::TEXT_TYPE, substr($this->code, $this->cursor));
            $this->cursor = $this->end;
            return;
        }
        // Find the first token after the current cursor
        $position = $this->positions[0][++$this->position];
        while ($position[1] < $this->cursor) {
            if ($this->position == count($this->positions[0]) - 1) {
                return;
            }
            $position = $this->positions[0][++$this->position];
        }
        // push the template text first
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
                // raw data?
                if (preg_match($this->regexes['lex_block_raw'], $this->code, $match, null, $this->cursor)) {
                    $this->moveCursor($match[0]);
                    $this->lexRawData();
                // {% line \d+ %}
                } elseif (preg_match($this->regexes['lex_block_line'], $this->code, $match, null, $this->cursor)) {
                    $this->moveCursor($match[0]);
                    $this->lineno = (int) $match[1];
                } else {
                    $this->pushToken(Twig_Token::BLOCK_START_TYPE);
                    $this->pushState(self::STATE_BLOCK);
                }
                break;
            case $this->options['tag_variable'][0]:
                $this->pushToken(Twig_Token::VAR_START_TYPE);
                $this->pushState(self::STATE_VAR);
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
        // whitespace
        if (preg_match('/\s+/A', $this->code, $match, null, $this->cursor)) {
            $this->moveCursor($match[0]);
            if ($this->cursor >= $this->end) {
                throw new Twig_Error_Syntax(sprintf('Unexpected end of file: Unclosed "%s"', $this->state === self::STATE_BLOCK ? 'block' : 'variable'), $this->lineno, $this->filename);
            }
        }
        // operators
        if (preg_match($this->regexes['operator'], $this->code, $match, null, $this->cursor)) {
            $this->pushToken(Twig_Token::OPERATOR_TYPE, $match[0]);
            $this->moveCursor($match[0]);
        }
        // names
        elseif (preg_match(self::REGEX_NAME, $this->code, $match, null, $this->cursor)) {
            $this->pushToken(Twig_Token::NAME_TYPE, $match[0]);
            $this->moveCursor($match[0]);
        }
        // numbers
        elseif (preg_match(self::REGEX_NUMBER, $this->code, $match, null, $this->cursor)) {
            $number = (float) $match[0];  // floats
            if (ctype_digit($match[0]) && $number <= PHP_INT_MAX) {
                $number = (int) $match[0]; // integers lower than the maximum
            }
            $this->pushToken(Twig_Token::NUMBER_TYPE, $number);
            $this->moveCursor($match[0]);
        }
        // punctuation
        elseif (false !== strpos(self::PUNCTUATION, $this->code[$this->cursor])) {
            // opening bracket
            if (false !== strpos('([{', $this->code[$this->cursor])) {
                $this->brackets[] = array($this->code[$this->cursor], $this->lineno);
            }
            // closing bracket
            elseif (false !== strpos(')]}', $this->code[$this->cursor])) {
                if (empty($this->brackets)) {
                    throw new Twig_Error_Syntax(sprintf('Unexpected "%s"', $this->code[$this->cursor]), $this->lineno, $this->filename);
                }
                list($expect, $lineno) = array_pop($this->brackets);
                if ($this->code[$this->cursor] != strtr($expect, '([{', ')]}')) {
                    throw new Twig_Error_Syntax(sprintf('Unclosed "%s"', $expect), $lineno, $this->filename);
                }
            }
            $this->pushToken(Twig_Token::PUNCTUATION_TYPE, $this->code[$this->cursor]);
            ++$this->cursor;
        }
        // strings
        elseif (preg_match(self::REGEX_STRING, $this->code, $match, null, $this->cursor)) {
            $this->pushToken(Twig_Token::STRING_TYPE, stripcslashes(substr($match[0], 1, -1)));
            $this->moveCursor($match[0]);
        }
        // opening double quoted string
        elseif (preg_match(self::REGEX_DQ_STRING_DELIM, $this->code, $match, null, $this->cursor)) {
            $this->brackets[] = array('"', $this->lineno);
            $this->pushState(self::STATE_STRING);
            $this->moveCursor($match[0]);
        }
        // unlexable
        else {
            throw new Twig_Error_Syntax(sprintf('Unexpected character "%s"', $this->code[$this->cursor]), $this->lineno, $this->filename);
        }
    }
    protected function lexRawData()
    {
        if (!preg_match($this->regexes['lex_raw_data'], $this->code, $match, PREG_OFFSET_CAPTURE, $this->cursor)) {
            throw new Twig_Error_Syntax(sprintf('Unexpected end of file: Unclosed "block"'), $this->lineno, $this->filename);
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
            if ($this->code[$this->cursor] != '"') {
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
    protected function pushToken($type, $value = '')
    {
        // do not push empty text tokens
        if (Twig_Token::TEXT_TYPE === $type && '' === $value) {
            return;
        }
        $this->tokens[] = new Twig_Token($type, $value, $this->lineno);
    }
    protected function moveCursor($text)
    {
        $this->cursor += strlen($text);
        $this->lineno += substr_count($text, "\n");
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
            // an operator that ends with a character must be followed by
            // a whitespace or a parenthesis
            if (ctype_alpha($operator[$length - 1])) {
                $regex[] = preg_quote($operator, '/').'(?=[\s()])';
            } else {
                $regex[] = preg_quote($operator, '/');
            }
        }
        return '/'.implode('|', $regex).'/A';
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Interface all loaders must implement.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
interface Twig_LoaderInterface
{
    /**
     * Gets the source code of a template, given its name.
     *
     * @param string $name The name of the template to load
     *
     * @return string The template source code
     *
     * @throws Twig_Error_Loader When $name is not found
     */
    function getSource($name);
    /**
     * Gets the cache key to use for the cache for a given template name.
     *
     * @param string $name The name of the template to load
     *
     * @return string The cache key
     *
     * @throws Twig_Error_Loader When $name is not found
     */
    function getCacheKey($name);
    /**
     * Returns true if the template is still fresh.
     *
     * @param string    $name The template name
     * @param timestamp $time The last modification time of the cached template
     *
     * @return Boolean true if the template is fresh, false otherwise
     *
     * @throws Twig_Error_Loader When $name is not found
     */
    function isFresh($name, $time);
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Loads a template from an array.
 *
 * When using this loader with a cache mechanism, you should know that a new cache
 * key is generated each time a template content "changes" (the cache key being the
 * source code of the template). If you don't want to see your cache grows out of
 * control, you need to take care of clearing the old cache file by yourself.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Loader_Array implements Twig_LoaderInterface
{
    protected $templates;
    /**
     * Constructor.
     *
     * @param array $templates An array of templates (keys are the names, and values are the source code)
     *
     * @see Twig_Loader
     */
    public function __construct(array $templates)
    {
        $this->templates = array();
        foreach ($templates as $name => $template) {
            $this->templates[$name] = $template;
        }
    }
    /**
     * Adds or overrides a template.
     *
     * @param string $name     The template name
     * @param string $template The template source
     */
    public function setTemplate($name, $template)
    {
        $this->templates[(string) $name] = $template;
    }
    /**
     * Gets the source code of a template, given its name.
     *
     * @param string $name The name of the template to load
     *
     * @return string The template source code
     */
    public function getSource($name)
    {
        $name = (string) $name;
        if (!isset($this->templates[$name])) {
            throw new Twig_Error_Loader(sprintf('Template "%s" is not defined.', $name));
        }
        return $this->templates[$name];
    }
    /**
     * Gets the cache key to use for the cache for a given template name.
     *
     * @param string $name The name of the template to load
     *
     * @return string The cache key
     */
    public function getCacheKey($name)
    {
        $name = (string) $name;
        if (!isset($this->templates[$name])) {
            throw new Twig_Error_Loader(sprintf('Template "%s" is not defined.', $name));
        }
        return $this->templates[$name];
    }
    /**
     * Returns true if the template is still fresh.
     *
     * @param string    $name The template name
     * @param timestamp $time The last modification time of the cached template
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Loads templates from other loaders.
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
class Twig_Loader_Chain implements Twig_LoaderInterface
{
    protected $loaders;
    /**
     * Constructor.
     *
     * @param Twig_LoaderInterface[] $loaders An array of loader instances
     */
    public function __construct(array $loaders = array())
    {
        $this->loaders = array();
        foreach ($loaders as $loader) {
            $this->addLoader($loader);
        }
    }
    /**
     * Adds a loader instance.
     *
     * @param Twig_LoaderInterface $loader A Loader instance
     */
    public function addLoader(Twig_LoaderInterface $loader)
    {
        $this->loaders[] = $loader;
    }
    /**
     * Gets the source code of a template, given its name.
     *
     * @param string $name The name of the template to load
     *
     * @return string The template source code
     */
    public function getSource($name)
    {
        $exceptions = array();
        foreach ($this->loaders as $loader) {
            try {
                return $loader->getSource($name);
            } catch (Twig_Error_Loader $e) {
                $exceptions[] = $e->getMessage();
            }
        }
        throw new Twig_Error_Loader(sprintf('Template "%s" is not defined (%s).', $name, implode(', ', $exceptions)));
    }
    /**
     * Gets the cache key to use for the cache for a given template name.
     *
     * @param string $name The name of the template to load
     *
     * @return string The cache key
     */
    public function getCacheKey($name)
    {
        $exceptions = array();
        foreach ($this->loaders as $loader) {
            try {
                return $loader->getCacheKey($name);
            } catch (Twig_Error_Loader $e) {
                $exceptions[] = get_class($loader).': '.$e->getMessage();
            }
        }
        throw new Twig_Error_Loader(sprintf('Template "%s" is not defined (%s).', $name, implode(' ', $exceptions)));
    }
    /**
     * Returns true if the template is still fresh.
     *
     * @param string    $name The template name
     * @param timestamp $time The last modification time of the cached template
     */
    public function isFresh($name, $time)
    {
        $exceptions = array();
        foreach ($this->loaders as $loader) {
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Loads template from the filesystem.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Loader_Filesystem implements Twig_LoaderInterface
{
    protected $paths;
    protected $cache;
    /**
     * Constructor.
     *
     * @param string|array $paths A path or an array of paths where to look for templates
     */
    public function __construct($paths)
    {
        $this->setPaths($paths);
    }
    /**
     * Returns the paths to the templates.
     *
     * @param string $namespace A path namespace
     *
     * @return array The array of paths where to look for templates
     */
    public function getPaths($namespace = '__main__')
    {
        return isset($this->paths[$namespace]) ? $this->paths[$namespace] : array();
    }
    /**
     * Returns the path namespaces.
     *
     * The "__main__" namespace is always defined.
     *
     * @return array The array of defined namespaces
     */
    public function getNamespaces()
    {
        return array_keys($this->paths);
    }
    /**
     * Sets the paths where templates are stored.
     *
     * @param string|array $paths     A path or an array of paths where to look for templates
     * @param string       $namespace A path namespace
     */
    public function setPaths($paths, $namespace = '__main__')
    {
        if (!is_array($paths)) {
            $paths = array($paths);
        }
        $this->paths[$namespace] = array();
        foreach ($paths as $path) {
            $this->addPath($path, $namespace);
        }
    }
    /**
     * Adds a path where templates are stored.
     *
     * @param string $path      A path where to look for templates
     * @param string $namespace A path name
     */
    public function addPath($path, $namespace = '__main__')
    {
        // invalidate the cache
        $this->cache = array();
        if (!is_dir($path)) {
            throw new Twig_Error_Loader(sprintf('The "%s" directory does not exist.', $path));
        }
        $this->paths[$namespace][] = rtrim($path, '/\\');
    }
    /**
     * Prepends a path where templates are stored.
     *
     * @param string $path      A path where to look for templates
     * @param string $namespace A path name
     */
    public function prependPath($path, $namespace = '__main__')
    {
        // invalidate the cache
        $this->cache = array();
        if (!is_dir($path)) {
            throw new Twig_Error_Loader(sprintf('The "%s" directory does not exist.', $path));
        }
        $path = rtrim($path, '/\\');
        if (!isset($this->paths[$namespace])) {
            $this->paths[$namespace][] = $path;
        } else {
            array_unshift($this->paths[$namespace], $path);
        }
    }
    /**
     * Gets the source code of a template, given its name.
     *
     * @param string $name The name of the template to load
     *
     * @return string The template source code
     */
    public function getSource($name)
    {
        return file_get_contents($this->findTemplate($name));
    }
    /**
     * Gets the cache key to use for the cache for a given template name.
     *
     * @param string $name The name of the template to load
     *
     * @return string The cache key
     */
    public function getCacheKey($name)
    {
        return $this->findTemplate($name);
    }
    /**
     * Returns true if the template is still fresh.
     *
     * @param string    $name The template name
     * @param timestamp $time The last modification time of the cached template
     */
    public function isFresh($name, $time)
    {
        return filemtime($this->findTemplate($name)) <= $time;
    }
    protected function findTemplate($name)
    {
        // normalize name
        $name = preg_replace('#/{2,}#', '/', strtr($name, '\\', '/'));
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }
        $this->validateName($name);
        $namespace = '__main__';
        if (isset($name[0]) && '@' == $name[0]) {
            if (false === $pos = strpos($name, '/')) {
                throw new InvalidArgumentException(sprintf('Malformed namespaced template name "%s" (expecting "@namespace/template_name").', $name));
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
        if (false !== strpos($name, "\0")) {
            throw new Twig_Error_Loader('A template name cannot contain NUL bytes.');
        }
        $parts = explode('/', $name);
        $level = 0;
        foreach ($parts as $part) {
            if ('..' === $part) {
                --$level;
            } elseif ('.' !== $part) {
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Loads a template from a string.
 *
 * This loader should only be used for unit testing as it has many limitations
 * (for instance, the include or extends tag does not make any sense for a string
 * loader).
 *
 * When using this loader with a cache mechanism, you should know that a new cache
 * key is generated each time a template content "changes" (the cache key being the
 * source code of the template). If you don't want to see your cache grows out of
 * control, you need to take care of clearing the old cache file by yourself.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Loader_String implements Twig_LoaderInterface
{
    /**
     * Gets the source code of a template, given its name.
     *
     * @param string $name The name of the template to load
     *
     * @return string The template source code
     */
    public function getSource($name)
    {
        return $name;
    }
    /**
     * Gets the cache key to use for the cache for a given template name.
     *
     * @param string $name The name of the template to load
     *
     * @return string The cache key
     */
    public function getCacheKey($name)
    {
        return $name;
    }
    /**
     * Returns true if the template is still fresh.
     *
     * @param string    $name The template name
     * @param timestamp $time The last modification time of the cached template
     */
    public function isFresh($name, $time)
    {
        return true;
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Marks a content as safe.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a node in the AST.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
interface Twig_NodeInterface extends Countable, IteratorAggregate
{
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
    function compile(Twig_Compiler $compiler);
    function getLine();
    function getNodeTag();
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a node in the AST.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node implements Twig_NodeInterface
{
    protected $nodes;
    protected $attributes;
    protected $lineno;
    protected $tag;
    /**
     * Constructor.
     *
     * The nodes are automatically made available as properties ($this->node).
     * The attributes are automatically made available as array items ($this['name']).
     *
     * @param array   $nodes      An array of named nodes
     * @param array   $attributes An array of attributes (should not be nodes)
     * @param integer $lineno     The line number
     * @param string  $tag        The tag name associated with the Node
     */
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
            $attributes[] = sprintf('%s: %s', $name, str_replace("\n", '', var_export($value, true)));
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
            $repr[] = ')';
        } else {
            $repr[0] .= ')';
        }
        return implode("\n", $repr);
    }
    public function toXml($asDom = false)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
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
    /**
     * Returns true if the attribute is defined.
     *
     * @param  string  The attribute name
     *
     * @return Boolean true if the attribute is defined, false otherwise
     */
    public function hasAttribute($name)
    {
        return array_key_exists($name, $this->attributes);
    }
    /**
     * Gets an attribute.
     *
     * @param  string The attribute name
     *
     * @return mixed  The attribute value
     */
    public function getAttribute($name)
    {
        if (!array_key_exists($name, $this->attributes)) {
            throw new Twig_Error_Runtime(sprintf('Attribute "%s" does not exist for Node "%s".', $name, get_class($this)));
        }
        return $this->attributes[$name];
    }
    /**
     * Sets an attribute.
     *
     * @param string The attribute name
     * @param mixed  The attribute value
     */
    public function setAttribute($name, $value)
    {
        $this->attributes[$name] = $value;
    }
    /**
     * Removes an attribute.
     *
     * @param string The attribute name
     */
    public function removeAttribute($name)
    {
        unset($this->attributes[$name]);
    }
    /**
     * Returns true if the node with the given identifier exists.
     *
     * @param  string  The node name
     *
     * @return Boolean true if the node with the given name exists, false otherwise
     */
    public function hasNode($name)
    {
        return array_key_exists($name, $this->nodes);
    }
    /**
     * Gets a node by name.
     *
     * @param  string The node name
     *
     * @return Twig_Node A Twig_Node instance
     */
    public function getNode($name)
    {
        if (!array_key_exists($name, $this->nodes)) {
            throw new Twig_Error_Runtime(sprintf('Node "%s" does not exist for Node "%s".', $name, get_class($this)));
        }
        return $this->nodes[$name];
    }
    /**
     * Sets a node.
     *
     * @param string    The node name
     * @param Twig_Node A Twig_Node instance
     */
    public function setNode($name, $node = null)
    {
        $this->nodes[$name] = $node;
    }
    /**
     * Removes a node by name.
     *
     * @param string The node name
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents an autoescape node.
 *
 * The value is the escaping strategy (can be html, js, ...)
 *
 * The true value is equivalent to html.
 *
 * If autoescaping is disabled, then the value is false.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_AutoEscape extends Twig_Node
{
    public function __construct($value, Twig_NodeInterface $body, $lineno, $tag = 'autoescape')
    {
        parent::__construct(array('body' => $body), array('value' => $value), $lineno, $tag);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
    public function compile(Twig_Compiler $compiler)
    {
        $compiler->subcompile($this->getNode('body'));
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a block node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Block extends Twig_Node
{
    public function __construct($name, Twig_NodeInterface $body, $lineno, $tag = null)
    {
        parent::__construct(array('body' => $body), array('name' => $name), $lineno, $tag);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
    public function compile(Twig_Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write(sprintf("public function block_%s(\$context, array \$blocks = array())\n", $this->getAttribute('name')), "{\n")
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a displayable node in the AST.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
interface Twig_NodeOutputInterface
{
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a block call node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_BlockReference extends Twig_Node implements Twig_NodeOutputInterface
{
    public function __construct($name, $lineno, $tag = null)
    {
        parent::__construct(array(), array('name' => $name), $lineno, $tag);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a body node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Body extends Twig_Node
{
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a do node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Do extends Twig_Node
{
    public function __construct(Twig_Node_Expression $expr, $lineno, $tag = null)
    {
        parent::__construct(array('expr' => $expr), array(), $lineno, $tag);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents an include node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Include extends Twig_Node implements Twig_NodeOutputInterface
{
    public function __construct(Twig_Node_Expression $expr, Twig_Node_Expression $variables = null, $only = false, $ignoreMissing = false, $lineno, $tag = null)
    {
        parent::__construct(array('expr' => $expr, 'variables' => $variables), array('only' => (Boolean) $only, 'ignore_missing' => (Boolean) $ignoreMissing), $lineno, $tag);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2012 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents an embed node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Embed extends Twig_Node_Include
{
    // we don't inject the module to avoid node visitors to traverse it twice (as it will be already visited in the main module)
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Abstract class for all nodes that represents an expression.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
abstract class Twig_Node_Expression extends Twig_Node
{
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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
            $pairs[] = array(
                'key' => $pair[0],
                'value' => $pair[1],
            );
        }
        return $pairs;
    }
    public function hasElement(Twig_Node_Expression $key)
    {
        foreach ($this->getKeyValuePairs() as $pair) {
            // we compare the string representation of the keys
            // to avoid comparing the line numbers which are not relevant here.
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
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Node_Expression_Name extends Twig_Node_Expression
{
    protected $specialVars = array(
        '_self'    => '$this',
        '_context' => '$context',
        '_charset' => '$this->env->getCharset()',
    );
    public function __construct($name, $lineno)
    {
        parent::__construct(array(), array('name' => $name, 'is_defined_test' => false, 'ignore_strict_check' => false), $lineno);
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
        } else {
            // remove the non-PHP 5.4 version when PHP 5.3 support is dropped
            // as the non-optimized version is just a workaround for slow ternary operator
            // when the context has a lot of variables
            if (version_compare(phpversion(), '5.4.0RC1', '>=') && ($this->getAttribute('ignore_strict_check') || !$compiler->getEnvironment()->isStrictVariables())) {
                // PHP 5.4 ternary operator performance was optimized
                $compiler
                    ->raw('(isset($context[')
                    ->string($name)
                    ->raw(']) ? $context[')
                    ->string($name)
                    ->raw('] : null)')
                ;
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Node_Expression_AssignName extends Twig_Node_Expression_Name
{
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
abstract class Twig_Node_Expression_Binary extends Twig_Node_Expression
{
    public function __construct(Twig_NodeInterface $left, Twig_NodeInterface $right, $lineno)
    {
        parent::__construct(array('left' => $left, 'right' => $right), array(), $lineno);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Node_Expression_Binary_FloorDiv extends Twig_Node_Expression_Binary
{
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Node_Expression_Binary_In extends Twig_Node_Expression_Binary
{
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Node_Expression_Binary_NotIn extends Twig_Node_Expression_Binary
{
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Node_Expression_Binary_Power extends Twig_Node_Expression_Binary
{
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Node_Expression_Binary_Range extends Twig_Node_Expression_Binary
{
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a block call node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Expression_BlockReference extends Twig_Node_Expression
{
    public function __construct(Twig_NodeInterface $name, $asString = false, $lineno, $tag = null)
    {
        parent::__construct(array('name' => $name), array('as_string' => $asString, 'output' => false), $lineno, $tag);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Node_Expression_Conditional extends Twig_Node_Expression
{
    public function __construct(Twig_Node_Expression $expr1, Twig_Node_Expression $expr2, Twig_Node_Expression $expr3, $lineno)
    {
        parent::__construct(array('expr1' => $expr1, 'expr2' => $expr2, 'expr3' => $expr3), array(), $lineno);
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Node_Expression_Constant extends Twig_Node_Expression
{
    public function __construct($value, $lineno)
    {
        parent::__construct(array(), array('value' => $value), $lineno);
    }
    public function compile(Twig_Compiler $compiler)
    {
        $compiler->repr($this->getAttribute('value'));
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents an extension call node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Expression_ExtensionReference extends Twig_Node_Expression
{
    public function __construct($name, $lineno, $tag = null)
    {
        parent::__construct(array(), array('name' => $name), $lineno, $tag);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
    public function compile(Twig_Compiler $compiler)
    {
        $compiler->raw(sprintf("\$this->env->getExtension('%s')", $this->getAttribute('name')));
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Node_Expression_Filter extends Twig_Node_Expression
{
    public function __construct(Twig_NodeInterface $node, Twig_Node_Expression_Constant $filterName, Twig_NodeInterface $arguments, $lineno, $tag = null)
    {
        parent::__construct(array('node' => $node, 'filter' => $filterName, 'arguments' => $arguments), array(), $lineno, $tag);
    }
    public function compile(Twig_Compiler $compiler)
    {
        $name = $this->getNode('filter')->getAttribute('value');
        if (false === $filter = $compiler->getEnvironment()->getFilter($name)) {
            $message = sprintf('The filter "%s" does not exist', $name);
            if ($alternatives = $compiler->getEnvironment()->computeAlternatives($name, array_keys($compiler->getEnvironment()->getFilters()))) {
                $message = sprintf('%s. Did you mean "%s"', $message, implode('", "', $alternatives));
            }
            throw new Twig_Error_Syntax($message, $this->getLine());
        }
        $this->compileFilter($compiler, $filter);
    }
    protected function compileFilter(Twig_Compiler $compiler, Twig_FilterInterface $filter)
    {
        $compiler
            ->raw($filter->compile().'(')
            ->raw($filter->needsEnvironment() ? '$this->env, ' : '')
            ->raw($filter->needsContext() ? '$context, ' : '')
        ;
        foreach ($filter->getArguments() as $argument) {
            $compiler
                ->string($argument)
                ->raw(', ')
            ;
        }
        $compiler->subcompile($this->getNode('node'));
        foreach ($this->getNode('arguments') as $node) {
            $compiler
                ->raw(', ')
                ->subcompile($node)
            ;
        }
        $compiler->raw(')');
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Returns the value or the default value when it is undefined or empty.
 *
 * <pre>
 *  {{ var.foo|default('foo item on var is not defined') }}
 * </pre>
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Expression_Filter_Default extends Twig_Node_Expression_Filter
{
    public function __construct(Twig_NodeInterface $node, Twig_Node_Expression_Constant $filterName, Twig_NodeInterface $arguments, $lineno, $tag = null)
    {
        $default = new Twig_Node_Expression_Filter($node, new Twig_Node_Expression_Constant('_default', $node->getLine()), $arguments, $node->getLine());
        if ('default' === $filterName->getAttribute('value') && ($node instanceof Twig_Node_Expression_Name || $node instanceof Twig_Node_Expression_GetAttr)) {
            $test = new Twig_Node_Expression_Test_Defined(clone $node, 'defined', new Twig_Node(), $node->getLine());
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Node_Expression_Function extends Twig_Node_Expression
{
    public function __construct($name, Twig_NodeInterface $arguments, $lineno)
    {
        parent::__construct(array('arguments' => $arguments), array('name' => $name), $lineno);
    }
    public function compile(Twig_Compiler $compiler)
    {
        $name = $this->getAttribute('name');
        if (false === $function = $compiler->getEnvironment()->getFunction($name)) {
            $message = sprintf('The function "%s" does not exist', $name);
            if ($alternatives = $compiler->getEnvironment()->computeAlternatives($name, array_keys($compiler->getEnvironment()->getFunctions()))) {
                $message = sprintf('%s. Did you mean "%s"', $message, implode('", "', $alternatives));
            }
            throw new Twig_Error_Syntax($message, $this->getLine());
        }
        $compiler->raw($function->compile().'(');
        $first = true;
        if ($function->needsEnvironment()) {
            $compiler->raw('$this->env');
            $first = false;
        }
        if ($function->needsContext()) {
            if (!$first) {
                $compiler->raw(', ');
            }
            $compiler->raw('$context');
            $first = false;
        }
        foreach ($function->getArguments() as $argument) {
            if (!$first) {
                $compiler->raw(', ');
            }
            $compiler->string($argument);
            $first = false;
        }
        foreach ($this->getNode('arguments') as $node) {
            if (!$first) {
                $compiler->raw(', ');
            }
            $compiler->subcompile($node);
            $first = false;
        }
        $compiler->raw(')');
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Node_Expression_GetAttr extends Twig_Node_Expression
{
    public function __construct(Twig_Node_Expression $node, Twig_Node_Expression $attribute, Twig_Node_Expression_Array $arguments, $type, $lineno)
    {
        parent::__construct(array('node' => $node, 'attribute' => $attribute, 'arguments' => $arguments), array('type' => $type, 'is_defined_test' => false, 'ignore_strict_check' => false), $lineno);
    }
    public function compile(Twig_Compiler $compiler)
    {
        if (function_exists('twig_template_get_attributes')) {
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
                $compiler->raw(', '.($this->getAttribute('is_defined_test') ? 'true' : 'false'));
            }
            if ($this->getAttribute('ignore_strict_check')) {
                $compiler->raw(', '.($this->getAttribute('ignore_strict_check') ? 'true' : 'false'));
            }
        }
        $compiler->raw(')');
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2012 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Node_Expression_MethodCall extends Twig_Node_Expression
{
    public function __construct(Twig_Node_Expression $node, $method, Twig_Node_Expression_Array $arguments, $lineno)
    {
        parent::__construct(array('node' => $node, 'arguments' => $arguments), array('method' => $method, 'safe' => false), $lineno);
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a parent node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Expression_Parent extends Twig_Node_Expression
{
    public function __construct($name, $lineno, $tag = null)
    {
        parent::__construct(array(), array('output' => false, 'name' => $name), $lineno, $tag);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Node_Expression_TempName extends Twig_Node_Expression
{
    public function __construct($name, $lineno)
    {
        parent::__construct(array(), array('name' => $name), $lineno);
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Node_Expression_Test extends Twig_Node_Expression
{
    public function __construct(Twig_NodeInterface $node, $name, Twig_NodeInterface $arguments = null, $lineno)
    {
        parent::__construct(array('node' => $node, 'arguments' => $arguments), array('name' => $name), $lineno);
    }
    public function compile(Twig_Compiler $compiler)
    {
        $name = $this->getAttribute('name');
        $testMap = $compiler->getEnvironment()->getTests();
        if (!isset($testMap[$name])) {
            $message = sprintf('The test "%s" does not exist', $name);
            if ($alternatives = $compiler->getEnvironment()->computeAlternatives($name, array_keys($compiler->getEnvironment()->getTests()))) {
                $message = sprintf('%s. Did you mean "%s"', $message, implode('", "', $alternatives));
            }
            throw new Twig_Error_Syntax($message, $this->getLine());
        }
        $name = $this->getAttribute('name');
        $node = $this->getNode('node');
        $compiler
            ->raw($testMap[$name]->compile().'(')
            ->subcompile($node)
        ;
        if (null !== $this->getNode('arguments')) {
            $compiler->raw(', ');
            $max = count($this->getNode('arguments')) - 1;
            foreach ($this->getNode('arguments') as $i => $arg) {
                $compiler->subcompile($arg);
                if ($i != $max) {
                    $compiler->raw(', ');
                }
            }
        }
        $compiler->raw(')');
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Checks if a variable is the exact same value as a constant.
 *
 * <pre>
 *  {% if post.status is constant('Post::PUBLISHED') %}
 *    the status attribute is exactly the same as Post::PUBLISHED
 *  {% endif %}
 * </pre>
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Expression_Test_Constant extends Twig_Node_Expression_Test
{
    public function compile(Twig_Compiler $compiler)
    {
        $compiler
            ->raw('(')
            ->subcompile($this->getNode('node'))
            ->raw(' === constant(')
            ->subcompile($this->getNode('arguments')->getNode(0))
            ->raw('))')
        ;
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Checks if a variable is defined in the current context.
 *
 * <pre>
 * {# defined works with variable names and variable attributes #}
 * {% if foo is defined %}
 *     {# ... #}
 * {% endif %}
 * </pre>
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Checks if a variable is divisible by a number.
 *
 * <pre>
 *  {% if loop.index is divisibleby(3) %}
 * </pre>
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Checks if a number is even.
 *
 * <pre>
 *  {{ var is even }}
 * </pre>
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Checks that a variable is null.
 *
 * <pre>
 *  {{ var is none }}
 * </pre>
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Checks if a number is odd.
 *
 * <pre>
 *  {{ var is odd }}
 * </pre>
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Checks if a variable is the same as another one (=== in PHP).
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
abstract class Twig_Node_Expression_Unary extends Twig_Node_Expression
{
    public function __construct(Twig_NodeInterface $node, $lineno)
    {
        parent::__construct(array('node' => $node), array(), $lineno);
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a flush node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Flush extends Twig_Node
{
    public function __construct($lineno, $tag)
    {
        parent::__construct(array(), array(), $lineno, $tag);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a for node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_For extends Twig_Node
{
    protected $loop;
    public function __construct(Twig_Node_Expression_AssignName $keyTarget, Twig_Node_Expression_AssignName $valueTarget, Twig_Node_Expression $seq, Twig_Node_Expression $ifexpr = null, Twig_NodeInterface $body, Twig_NodeInterface $else = null, $lineno, $tag = null)
    {
        $body = new Twig_Node(array($body, $this->loop = new Twig_Node_ForLoop($lineno, $tag)));
        if (null !== $ifexpr) {
            $body = new Twig_Node_If(new Twig_Node(array($ifexpr, $body)), null, $lineno, $tag);
        }
        parent::__construct(array('key_target' => $keyTarget, 'value_target' => $valueTarget, 'seq' => $seq, 'body' => $body, 'else' => $else), array('with_loop' => true, 'ifexpr' => null !== $ifexpr), $lineno, $tag);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
    public function compile(Twig_Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            // the (array) cast bypasses a PHP 5.2.6 bug
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
        // remove some "private" loop variables (needed for nested loops)
        $compiler->write('unset($context[\'_seq\'], $context[\'_iterated\'], $context[\''.$this->getNode('key_target')->getAttribute('name').'\'], $context[\''.$this->getNode('value_target')->getAttribute('name').'\'], $context[\'_parent\'], $context[\'loop\']);'."\n");
        // keep the values set in the inner context for variables defined in the outer context
        $compiler->write("\$context = array_merge(\$_parent, array_intersect_key(\$context, \$_parent));\n");
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Internal node used by the for node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_ForLoop extends Twig_Node
{
    public function __construct($lineno, $tag = null)
    {
        parent::__construct(array(), array('with_loop' => false, 'ifexpr' => false, 'else' => false), $lineno, $tag);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents an if node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_If extends Twig_Node
{
    public function __construct(Twig_NodeInterface $tests, Twig_NodeInterface $else = null, $lineno, $tag = null)
    {
        parent::__construct(array('tests' => $tests, 'else' => $else), array(), $lineno, $tag);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents an import node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Import extends Twig_Node
{
    public function __construct(Twig_Node_Expression $expr, Twig_Node_Expression $var, $lineno, $tag = null)
    {
        parent::__construct(array('expr' => $expr, 'var' => $var), array(), $lineno, $tag);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
    public function compile(Twig_Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write('')
            ->subcompile($this->getNode('var'))
            ->raw(' = ')
        ;
        if ($this->getNode('expr') instanceof Twig_Node_Expression_Name && '_self' === $this->getNode('expr')->getAttribute('name')) {
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a macro node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Macro extends Twig_Node
{
    public function __construct($name, Twig_NodeInterface $body, Twig_NodeInterface $arguments, $lineno, $tag = null)
    {
        parent::__construct(array('body' => $body, 'arguments' => $arguments), array('name' => $name), $lineno, $tag);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
    public function compile(Twig_Compiler $compiler)
    {
        $arguments = array();
        foreach ($this->getNode('arguments') as $argument) {
            $arguments[] = '$'.$argument->getAttribute('name').' = null';
        }
        $compiler
            ->addDebugInfo($this)
            ->write(sprintf("public function get%s(%s)\n", $this->getAttribute('name'), implode(', ', $arguments)), "{\n")
            ->indent()
        ;
        if (!count($this->getNode('arguments'))) {
            $compiler->write("\$context = \$this->env->getGlobals();\n\n");
        } else {
            $compiler
                ->write("\$context = \$this->env->mergeGlobals(array(\n")
                ->indent()
            ;
            foreach ($this->getNode('arguments') as $argument) {
                $compiler
                    ->write('')
                    ->string($argument->getAttribute('name'))
                    ->raw(' => $'.$argument->getAttribute('name'))
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
            ->write("} catch(Exception \$e) {\n")
            ->indent()
            ->write("ob_end_clean();\n\n")
            ->write("throw \$e;\n")
            ->outdent()
            ->write("}\n\n")
            ->write("return ob_get_clean();\n")
            ->outdent()
            ->write("}\n\n")
        ;
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a module node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Module extends Twig_Node
{
    public function __construct(Twig_NodeInterface $body, Twig_Node_Expression $parent = null, Twig_NodeInterface $blocks, Twig_NodeInterface $macros, Twig_NodeInterface $traits, $embeddedTemplates, $filename)
    {
        // embedded templates are set as attributes so that they are only visited once by the visitors
        parent::__construct(array('parent' => $parent, 'body' => $body, 'blocks' => $blocks, 'macros' => $macros, 'traits' => $traits), array('filename' => $filename, 'index' => null, 'embedded_templates' => $embeddedTemplates), 1);
    }
    public function setIndex($index)
    {
        $this->setAttribute('index', $index);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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
            ->write("protected function doGetParent(array \$context)\n", "{\n")
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
            // if the filename contains */, add a blank to avoid a PHP parse error
            ->write("/* ".str_replace('*/', '* /', $this->getAttribute('filename'))." */\n")
            ->write('class '.$compiler->getEnvironment()->getTemplateClass($this->getAttribute('filename'), $this->getAttribute('index')))
            ->raw(sprintf(" extends %s\n", $compiler->getEnvironment()->getBaseTemplateClass()))
            ->write("{\n")
            ->indent()
        ;
    }
    protected function compileConstructor(Twig_Compiler $compiler)
    {
        $compiler
            ->write("public function __construct(Twig_Environment \$env)\n", "{\n")
            ->indent()
            ->write("parent::__construct(\$env);\n\n")
        ;
        // parent
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
            // traits
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
                        ->write(sprintf("\$_trait_%s_blocks".($i == $countTraits - 1 ? '' : ',')."\n", $i))
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
        // blocks
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
            ->write("protected function doDisplay(array \$context, array \$blocks = array())\n", "{\n")
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
            ->write("public function getTemplateName()\n", "{\n")
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
        // A template can be used as a trait if:
        //   * it has no parent
        //   * it has no macros
        //   * it has no body
        //
        // Put another way, a template can be used as a trait if it
        // only contains blocks and use statements.
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
            ->write("public function isTraitable()\n", "{\n")
            ->indent()
            ->write(sprintf("return %s;\n", $traitable ? 'true' : 'false'))
            ->outdent()
            ->write("}\n\n")
        ;
    }
    protected function compileDebugInfo(Twig_Compiler $compiler)
    {
        $compiler
            ->write("public function getDebugInfo()\n", "{\n")
            ->indent()
            ->write(sprintf("return %s;\n", str_replace("\n", '', var_export(array_reverse($compiler->getDebugInfo(), true), true))))
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a node that outputs an expression.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Print extends Twig_Node implements Twig_NodeOutputInterface
{
    public function __construct(Twig_Node_Expression $expr, $lineno, $tag = null)
    {
        parent::__construct(array('expr' => $expr), array(), $lineno, $tag);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a sandbox node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Sandbox extends Twig_Node
{
    public function __construct(Twig_NodeInterface $body, $lineno, $tag = null)
    {
        parent::__construct(array('body' => $body), array(), $lineno, $tag);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a module node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
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
            ->write("protected function checkSecurity() {\n")
            ->indent()
            ->write("\$this->env->getExtension('sandbox')->checkSecurity(\n")
            ->indent()
            ->write(!$this->usedTags ? "array(),\n" : "array('".implode('\', \'', $this->usedTags)."'),\n")
            ->write(!$this->usedFilters ? "array(),\n" : "array('".implode('\', \'', $this->usedFilters)."'),\n")
            ->write(!$this->usedFunctions ? "array()\n" : "array('".implode('\', \'', $this->usedFunctions)."')\n")
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Twig_Node_SandboxedPrint adds a check for the __toString() method
 * when the variable is an object and the sandbox is activated.
 *
 * When there is a simple Print statement, like {{ article }},
 * and if the sandbox is enabled, we need to check that the __toString()
 * method is allowed if 'article' is an object.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_SandboxedPrint extends Twig_Node_Print
{
    public function __construct(Twig_Node_Expression $expr, $lineno, $tag = null)
    {
        parent::__construct($expr, $lineno, $tag);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
    public function compile(Twig_Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write('echo $this->env->getExtension(\'sandbox\')->ensureToStringAllowed(')
            ->subcompile($this->getNode('expr'))
            ->raw(");\n")
        ;
    }
    /**
     * Removes node filters.
     *
     * This is mostly needed when another visitor adds filters (like the escaper one).
     *
     * @param Twig_Node $node A Node
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a set node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Set extends Twig_Node
{
    public function __construct($capture, Twig_NodeInterface $names, Twig_NodeInterface $values, $lineno, $tag = null)
    {
        parent::__construct(array('names' => $names, 'values' => $values), array('capture' => $capture, 'safe' => false), $lineno, $tag);
        /*
         * Optimizes the node when capture is used for a large block of text.
         *
         * {% set foo %}foo{% endset %} is compiled to $context['foo'] = new Twig_Markup("foo");
         */
        if ($this->getAttribute('capture')) {
            $this->setAttribute('safe', true);
            $values = $this->getNode('values');
            if ($values instanceof Twig_Node_Text) {
                $this->setNode('values', new Twig_Node_Expression_Constant($values->getAttribute('data'), $values->getLine()));
                $this->setAttribute('capture', false);
            }
        }
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Node_SetTemp extends Twig_Node
{
    public function __construct($name, $lineno)
    {
        parent::__construct(array(), array('name' => $name), $lineno);
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

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a spaceless node.
 *
 * It removes spaces between HTML tags.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Spaceless extends Twig_Node
{
    public function __construct(Twig_NodeInterface $body, $lineno, $tag = 'spaceless')
    {
        parent::__construct(array('body' => $body), array(), $lineno, $tag);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a text node.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Node_Text extends Twig_Node implements Twig_NodeOutputInterface
{
    public function __construct($data, $lineno)
    {
        parent::__construct(array(), array('data' => $data), $lineno);
    }
    /**
     * Compiles the node to PHP.
     *
     * @param Twig_Compiler A Twig_Compiler instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Twig_NodeTraverser is a node traverser.
 *
 * It visits all nodes and their children and call the given visitor for each.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_NodeTraverser
{
    protected $env;
    protected $visitors;
    /**
     * Constructor.
     *
     * @param Twig_Environment $env      A Twig_Environment instance
     * @param array            $visitors An array of Twig_NodeVisitorInterface instances
     */
    public function __construct(Twig_Environment $env, array $visitors = array())
    {
        $this->env = $env;
        $this->visitors = array();
        foreach ($visitors as $visitor) {
            $this->addVisitor($visitor);
        }
    }
    /**
     * Adds a visitor.
     *
     * @param Twig_NodeVisitorInterface $visitor A Twig_NodeVisitorInterface instance
     */
    public function addVisitor(Twig_NodeVisitorInterface $visitor)
    {
        if (!isset($this->visitors[$visitor->getPriority()])) {
            $this->visitors[$visitor->getPriority()] = array();
        }
        $this->visitors[$visitor->getPriority()][] = $visitor;
    }
    /**
     * Traverses a node and calls the registered visitors.
     *
     * @param Twig_NodeInterface $node A Twig_NodeInterface instance
     */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Twig_NodeVisitorInterface is the interface the all node visitor classes must implement.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
interface Twig_NodeVisitorInterface
{
    /**
     * Called before child nodes are visited.
     *
     * @param Twig_NodeInterface $node The node to visit
     * @param Twig_Environment   $env  The Twig environment instance
     *
     * @return Twig_NodeInterface The modified node
     */
    function enterNode(Twig_NodeInterface $node, Twig_Environment $env);
    /**
     * Called after child nodes are visited.
     *
     * @param Twig_NodeInterface $node The node to visit
     * @param Twig_Environment   $env  The Twig environment instance
     *
     * @return Twig_NodeInterface The modified node
     */
    function leaveNode(Twig_NodeInterface $node, Twig_Environment $env);
    /**
     * Returns the priority for this visitor.
     *
     * Priority should be between -10 and 10 (0 is the default).
     *
     * @return integer The priority level
     */
    function getPriority();
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Twig_NodeVisitor_Escaper implements output escaping.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
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
    /**
     * Called before child nodes are visited.
     *
     * @param Twig_NodeInterface $node The node to visit
     * @param Twig_Environment   $env  The Twig environment instance
     *
     * @return Twig_NodeInterface The modified node
     */
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
    /**
     * Called after child nodes are visited.
     *
     * @param Twig_NodeInterface $node The node to visit
     * @param Twig_Environment   $env  The Twig environment instance
     *
     * @return Twig_NodeInterface The modified node
     */
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
        if (false !== $f = $env->getFilter($name)) {
            $type = $f->getPreEscape();
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
    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return 0;
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Twig_NodeVisitor_Optimizer tries to optimizes the AST.
 *
 * This visitor is always the last registered one.
 *
 * You can configure which optimizations you want to activate via the
 * optimizer mode.
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
class Twig_NodeVisitor_Optimizer implements Twig_NodeVisitorInterface
{
    const OPTIMIZE_ALL         = -1;
    const OPTIMIZE_NONE        = 0;
    const OPTIMIZE_FOR         = 2;
    const OPTIMIZE_RAW_FILTER  = 4;
    const OPTIMIZE_VAR_ACCESS  = 8;
    protected $loops = array();
    protected $optimizers;
    protected $prependedNodes = array();
    protected $inABody = false;
    /**
     * Constructor.
     *
     * @param integer $optimizers The optimizer mode
     */
    public function __construct($optimizers = -1)
    {
        if (!is_int($optimizers) || $optimizers > 2) {
            throw new InvalidArgumentException(sprintf('Optimizer mode "%s" is not valid.', $optimizers));
        }
        $this->optimizers = $optimizers;
    }
    /**
     * {@inheritdoc}
     */
    public function enterNode(Twig_NodeInterface $node, Twig_Environment $env)
    {
        if (self::OPTIMIZE_FOR === (self::OPTIMIZE_FOR & $this->optimizers)) {
            $this->enterOptimizeFor($node, $env);
        }
        if (!version_compare(phpversion(), '5.4.0RC1', '>=') && self::OPTIMIZE_VAR_ACCESS === (self::OPTIMIZE_VAR_ACCESS & $this->optimizers) && !$env->isStrictVariables() && !$env->hasExtension('sandbox')) {
            if ($this->inABody) {
                if (!$node instanceof Twig_Node_Expression) {
                    if (get_class($node) !== 'Twig_Node') {
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
    /**
     * {@inheritdoc}
     */
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
                if (!$expression && get_class($node) !== 'Twig_Node' && $prependedNodes = array_shift($this->prependedNodes)) {
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
        if ('Twig_Node_Expression_Name' === get_class($node) && $node->isSimple()) {
            $this->prependedNodes[0][] = $node->getAttribute('name');
            return new Twig_Node_Expression_TempName($node->getAttribute('name'), $node->getLine());
        }
        return $node;
    }
    /**
     * Optimizes print nodes.
     *
     * It replaces:
     *
     *   * "echo $this->render(Parent)Block()" with "$this->display(Parent)Block()"
     *
     * @param Twig_NodeInterface $node A Node
     * @param Twig_Environment   $env  The current Twig environment
     */
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
    /**
     * Removes "raw" filters.
     *
     * @param Twig_NodeInterface $node A Node
     * @param Twig_Environment   $env  The current Twig environment
     */
    protected function optimizeRawFilter($node, $env)
    {
        if ($node instanceof Twig_Node_Expression_Filter && 'raw' == $node->getNode('filter')->getAttribute('value')) {
            return $node->getNode('node');
        }
        return $node;
    }
    /**
     * Optimizes "for" tag by removing the "loop" variable creation whenever possible.
     *
     * @param Twig_NodeInterface $node A Node
     * @param Twig_Environment   $env  The current Twig environment
     */
    protected function enterOptimizeFor($node, $env)
    {
        if ($node instanceof Twig_Node_For) {
            // disable the loop variable by default
            $node->setAttribute('with_loop', false);
            array_unshift($this->loops, $node);
        } elseif (!$this->loops) {
            // we are outside a loop
            return;
        }
        // when do we need to add the loop variable back?
        // the loop variable is referenced for the current loop
        elseif ($node instanceof Twig_Node_Expression_Name && 'loop' === $node->getAttribute('name')) {
            $this->addLoopToCurrent();
        }
        // block reference
        elseif ($node instanceof Twig_Node_BlockReference || $node instanceof Twig_Node_Expression_BlockReference) {
            $this->addLoopToCurrent();
        }
        // include without the only attribute
        elseif ($node instanceof Twig_Node_Include && !$node->getAttribute('only')) {
            $this->addLoopToAll();
        }
        // the loop variable is referenced via an attribute
        elseif ($node instanceof Twig_Node_Expression_GetAttr
            && (!$node->getNode('attribute') instanceof Twig_Node_Expression_Constant
                || 'parent' === $node->getNode('attribute')->getAttribute('value')
               )
            && (true === $this->loops[0]->getAttribute('with_loop')
                || ($node->getNode('node') instanceof Twig_Node_Expression_Name
                    && 'loop' === $node->getNode('node')->getAttribute('name')
                   )
               )
        ) {
            $this->addLoopToAll();
        }
    }
    /**
     * Optimizes "for" tag by removing the "loop" variable creation whenever possible.
     *
     * @param Twig_NodeInterface $node A Node
     * @param Twig_Environment   $env  The current Twig environment
     */
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
    /**
     * {@inheritdoc}
     */
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
        return null;
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
        $this->data[$hash][] = array(
            'key' => $node,
            'value' => $safe,
        );
    }
    public function enterNode(Twig_NodeInterface $node, Twig_Environment $env)
    {
        return $node;
    }
    public function leaveNode(Twig_NodeInterface $node, Twig_Environment $env)
    {
        if ($node instanceof Twig_Node_Expression_Constant) {
            // constants are marked safe for all
            $this->setSafe($node, array('all'));
        } elseif ($node instanceof Twig_Node_Expression_BlockReference) {
            // blocks are safe by definition
            $this->setSafe($node, array('all'));
        } elseif ($node instanceof Twig_Node_Expression_Parent) {
            // parent block is safe by definition
            $this->setSafe($node, array('all'));
        } elseif ($node instanceof Twig_Node_Expression_Conditional) {
            // intersect safeness of both operands
            $safe = $this->intersectSafe($this->getSafe($node->getNode('expr2')), $this->getSafe($node->getNode('expr3')));
            $this->setSafe($node, $safe);
        } elseif ($node instanceof Twig_Node_Expression_Filter) {
            // filter expression is safe when the filter is safe
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
            // function expression is safe when the function is safe
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
            // attributes on template instances are safe
            if ('_self' == $name || in_array($name, $this->safeVars)) {
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
    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return 0;
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Twig_NodeVisitor_Sandbox implements sandboxing.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_NodeVisitor_Sandbox implements Twig_NodeVisitorInterface
{
    protected $inAModule = false;
    protected $tags;
    protected $filters;
    protected $functions;
    /**
     * Called before child nodes are visited.
     *
     * @param Twig_NodeInterface $node The node to visit
     * @param Twig_Environment   $env  The Twig environment instance
     *
     * @return Twig_NodeInterface The modified node
     */
    public function enterNode(Twig_NodeInterface $node, Twig_Environment $env)
    {
        // in a sandbox tag, only include tags are allowed
        if ($node instanceof Twig_Node_Sandbox && !$node->getNode('body') instanceof Twig_Node_Include) {
            foreach ($node->getNode('body') as $n) {
                if ($n instanceof Twig_Node_Text && ctype_space($n->getAttribute('data'))) {
                    continue;
                }
                if (!$n instanceof Twig_Node_Include) {
                    throw new Twig_Error_Syntax('Only "include" tags are allowed within a "sandbox" section', $n->getLine());
                }
            }
        }
        if ($node instanceof Twig_Node_Module) {
            $this->inAModule = true;
            $this->tags = array();
            $this->filters = array();
            $this->functions = array();
            return $node;
        } elseif ($this->inAModule) {
            // look for tags
            if ($node->getNodeTag()) {
                $this->tags[] = $node->getNodeTag();
            }
            // look for filters
            if ($node instanceof Twig_Node_Expression_Filter) {
                $this->filters[] = $node->getNode('filter')->getAttribute('value');
            }
            // look for functions
            if ($node instanceof Twig_Node_Expression_Function) {
                $this->functions[] = $node->getAttribute('name');
            }
            // wrap print to check __toString() calls
            if ($node instanceof Twig_Node_Print) {
                return new Twig_Node_SandboxedPrint($node->getNode('expr'), $node->getLine(), $node->getNodeTag());
            }
        }
        return $node;
    }
    /**
     * Called after child nodes are visited.
     *
     * @param Twig_NodeInterface $node The node to visit
     * @param Twig_Environment   $env  The Twig environment instance
     *
     * @return Twig_NodeInterface The modified node
     */
    public function leaveNode(Twig_NodeInterface $node, Twig_Environment $env)
    {
        if ($node instanceof Twig_Node_Module) {
            $this->inAModule = false;
            return new Twig_Node_SandboxedModule($node, array_unique($this->filters), array_unique($this->tags), array_unique($this->functions));
        }
        return $node;
    }
    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return 0;
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Interface implemented by parser classes.
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
interface Twig_ParserInterface
{
    /**
     * Converts a token stream to a node tree.
     *
     * @param Twig_TokenStream $stream A token stream instance
     *
     * @return Twig_Node_Module A node tree
     */
    function parse(Twig_TokenStream $stream);
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Default parser implementation.
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
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
    protected $importedFunctions;
    protected $traits;
    protected $embeddedTemplates = array();
    /**
     * Constructor.
     *
     * @param Twig_Environment $env A Twig_Environment instance
     */
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
    /**
     * Converts a token stream to a node tree.
     *
     * @param Twig_TokenStream $stream A token stream instance
     *
     * @return Twig_Node_Module A node tree
     */
    public function parse(Twig_TokenStream $stream, $test = null, $dropNeedle = false)
    {
        // push all variables into the stack to keep the current state of the parser
        $vars = get_object_vars($this);
        unset($vars['stack'], $vars['env'], $vars['handlers'], $vars['visitors'], $vars['expressionParser']);
        $this->stack[] = $vars;
        // tag handlers
        if (null === $this->handlers) {
            $this->handlers = $this->env->getTokenParsers();
            $this->handlers->setParser($this);
        }
        // node visitors
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
        $this->importedFunctions = array(array());
        $this->embeddedTemplates = array();
        try {
            $body = $this->subparse($test, $dropNeedle);
            if (null !== $this->parent) {
                if (null === $body = $this->filterBodyNodes($body)) {
                    $body = new Twig_Node();
                }
            }
        } catch (Twig_Error_Syntax $e) {
            if (null === $e->getTemplateFile()) {
                $e->setTemplateFile($this->stream->getFilename());
            }
            throw $e;
        }
        $node = new Twig_Node_Module(new Twig_Node_Body(array($body)), $this->parent, new Twig_Node($this->blocks), new Twig_Node($this->macros), new Twig_Node($this->traits), $this->embeddedTemplates, $this->stream->getFilename());
        $traverser = new Twig_NodeTraverser($this->env, $this->visitors);
        $node = $traverser->traverse($node);
        // restore previous stack so previous parse() call can resume working
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
                        throw new Twig_Error_Syntax('A block must start with a tag name', $token->getLine(), $this->stream->getFilename());
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
                            throw new Twig_Error_Syntax($error, $token->getLine(), $this->stream->getFilename());
                        }
                        $message = sprintf('Unknown tag name "%s"', $token->getValue());
                        if ($alternatives = $this->env->computeAlternatives($token->getValue(), array_keys($this->env->getTags()))) {
                            $message = sprintf('%s. Did you mean "%s"', $message, implode('", "', $alternatives));
                        }
                        throw new Twig_Error_Syntax($message, $token->getLine(), $this->stream->getFilename());
                    }
                    $this->stream->next();
                    $node = $subparser->parse($token);
                    if (null !== $node) {
                        $rv[] = $node;
                    }
                    break;
                default:
                    throw new Twig_Error_Syntax('Lexer or parser ended up in unsupported state.', -1, $this->stream->getFilename());
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
            throw new Twig_Error_Syntax(sprintf('"%s" cannot be used as a macro name as it is a reserved keyword', $name), $node->getLine());
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
    public function addImportedFunction($alias, $name, Twig_Node_Expression $node)
    {
        $this->importedFunctions[0][$alias] = array('name' => $name, 'node' => $node);
    }
    public function getImportedFunction($alias)
    {
        foreach ($this->importedFunctions as $functions) {
            if (isset($functions[$alias])) {
                return $functions[$alias];
            }
        }
    }
    public function isMainScope()
    {
        return 1 === count($this->importedFunctions);
    }
    public function pushLocalScope()
    {
        array_unshift($this->importedFunctions, array());
    }
    public function popLocalScope()
    {
        array_shift($this->importedFunctions);
    }
    /**
     * Gets the expression parser.
     *
     * @return Twig_ExpressionParser The expression parser
     */
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
    /**
     * Gets the token stream.
     *
     * @return Twig_TokenStream The token stream
     */
    public function getStream()
    {
        return $this->stream;
    }
    /**
     * Gets the current token.
     *
     * @return Twig_Token The current token
     */
    public function getCurrentToken()
    {
        return $this->stream->getCurrent();
    }
    protected function filterBodyNodes(Twig_NodeInterface $node)
    {
        // check that the body does not contain non-empty output nodes
        if (
            ($node instanceof Twig_Node_Text && !ctype_space($node->getAttribute('data')))
            ||
            (!$node instanceof Twig_Node_Text && !$node instanceof Twig_Node_BlockReference && $node instanceof Twig_NodeOutputInterface)
        ) {
            if (false !== strpos((string) $node, chr(0xEF).chr(0xBB).chr(0xBF))) {
                throw new Twig_Error_Syntax('A template that extends another one cannot have a body but a byte order mark (BOM) has been detected; it must be removed.', $node->getLine(), $this->stream->getFilename());
            }
            throw new Twig_Error_Syntax('A template that extends another one cannot have a body.', $node->getLine(), $this->stream->getFilename());
        }
        // bypass "set" nodes as they "capture" the output
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Exception thrown when a security error occurs at runtime.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
class Twig_Sandbox_SecurityError extends Twig_Error
{
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Interfaces that all security policy classes must implements.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
interface Twig_Sandbox_SecurityPolicyInterface
{
    function checkSecurity($tags, $filters, $functions);
    function checkMethodAllowed($obj, $method);
    function checkPropertyAllowed($obj, $method);
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a security policy which need to be enforced when sandbox mode is enabled.
 *
 * @package    twig
 * @author     Fabien Potencier <fabien@symfony.com>
 */
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Interface implemented by all compiled templates.
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
interface Twig_TemplateInterface
{
    const ANY_CALL    = 'any';
    const ARRAY_CALL  = 'array';
    const METHOD_CALL = 'method';
    /**
     * Renders the template with the given context and returns it as string.
     *
     * @param array $context An array of parameters to pass to the template
     *
     * @return string The rendered template
     */
    function render(array $context);
    /**
     * Displays the template with the given context.
     *
     * @param array $context An array of parameters to pass to the template
     * @param array $blocks  An array of blocks to pass to the template
     */
    function display(array $context, array $blocks = array());
    /**
     * Returns the bound environment for this template.
     *
     * @return Twig_Environment The current environment
     */
    function getEnvironment();
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Default base class for compiled templates.
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
abstract class Twig_Template implements Twig_TemplateInterface
{
    static protected $cache = array();
    protected $parent;
    protected $parents;
    protected $env;
    protected $blocks;
    protected $traits;
    /**
     * Constructor.
     *
     * @param Twig_Environment $env A Twig_Environment instance
     */
    public function __construct(Twig_Environment $env)
    {
        $this->env = $env;
        $this->blocks = array();
        $this->traits = array();
    }
    /**
     * Returns the template name.
     *
     * @return string The template name
     */
    abstract public function getTemplateName();
    /**
     * {@inheritdoc}
     */
    public function getEnvironment()
    {
        return $this->env;
    }
    /**
     * Returns the parent template.
     *
     * This method is for internal use only and should never be called
     * directly.
     *
     * @return Twig_TemplateInterface|false The parent template or false if there is no parent
     */
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
    /**
     * Displays a parent block.
     *
     * This method is for internal use only and should never be called
     * directly.
     *
     * @param string $name    The block name to display from the parent
     * @param array  $context The context
     * @param array  $blocks  The current set of blocks
     */
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
    /**
     * Displays a block.
     *
     * This method is for internal use only and should never be called
     * directly.
     *
     * @param string $name    The block name to display
     * @param array  $context The context
     * @param array  $blocks  The current set of blocks
     */
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
    /**
     * Renders a parent block.
     *
     * This method is for internal use only and should never be called
     * directly.
     *
     * @param string $name    The block name to render from the parent
     * @param array  $context The context
     * @param array  $blocks  The current set of blocks
     *
     * @return string The rendered block
     */
    public function renderParentBlock($name, array $context, array $blocks = array())
    {
        ob_start();
        $this->displayParentBlock($name, $context, $blocks);
        return ob_get_clean();
    }
    /**
     * Renders a block.
     *
     * This method is for internal use only and should never be called
     * directly.
     *
     * @param string $name    The block name to render
     * @param array  $context The context
     * @param array  $blocks  The current set of blocks
     *
     * @return string The rendered block
     */
    public function renderBlock($name, array $context, array $blocks = array())
    {
        ob_start();
        $this->displayBlock($name, $context, $blocks);
        return ob_get_clean();
    }
    /**
     * Returns whether a block exists or not.
     *
     * This method is for internal use only and should never be called
     * directly.
     *
     * This method does only return blocks defined in the current template
     * or defined in "used" traits.
     *
     * It does not return blocks from parent templates as the parent
     * template name can be dynamic, which is only known based on the
     * current context.
     *
     * @param string $name The block name
     *
     * @return Boolean true if the block exists, false otherwise
     */
    public function hasBlock($name)
    {
        return isset($this->blocks[(string) $name]);
    }
    /**
     * Returns all block names.
     *
     * This method is for internal use only and should never be called
     * directly.
     *
     * @return array An array of block names
     *
     * @see hasBlock
     */
    public function getBlockNames()
    {
        return array_keys($this->blocks);
    }
    /**
     * Returns all blocks.
     *
     * This method is for internal use only and should never be called
     * directly.
     *
     * @return array An array of blocks
     *
     * @see hasBlock
     */
    public function getBlocks()
    {
        return $this->blocks;
    }
    /**
     * {@inheritdoc}
     */
    public function display(array $context, array $blocks = array())
    {
        $this->displayWithErrorHandling($this->env->mergeGlobals($context), $blocks);
    }
    /**
     * {@inheritdoc}
     */
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
            throw $e;
        } catch (Exception $e) {
            throw new Twig_Error_Runtime(sprintf('An exception has been thrown during the rendering of a template ("%s").', $e->getMessage()), -1, null, $e);
        }
    }
    /**
     * Auto-generated method to display the template with the given context.
     *
     * @param array $context An array of parameters to pass to the template
     * @param array $blocks  An array of blocks to pass to the template
     */
    abstract protected function doDisplay(array $context, array $blocks = array());
    /**
     * Returns a variable from the context.
     *
     * This method is for internal use only and should never be called
     * directly.
     *
     * This method should not be overridden in a sub-class as this is an
     * implementation detail that has been introduced to optimize variable
     * access for versions of PHP before 5.4. This is not a way to override
     * the way to get a variable value.
     *
     * @param array   $context           The context
     * @param string  $item              The variable to return from the context
     * @param Boolean $ignoreStrictCheck Whether to ignore the strict variable check or not
     *
     * @return The content of the context variable
     *
     * @throws Twig_Error_Runtime if the variable does not exist and Twig is running in strict mode
     */
    final protected function getContext($context, $item, $ignoreStrictCheck = false)
    {
        if (!array_key_exists($item, $context)) {
            if ($ignoreStrictCheck || !$this->env->isStrictVariables()) {
                return null;
            }
            throw new Twig_Error_Runtime(sprintf('Variable "%s" does not exist', $item));
        }
        return $context[$item];
    }
    /**
     * Returns the attribute value for a given array/object.
     *
     * @param mixed   $object            The object or array from where to get the item
     * @param mixed   $item              The item to get from the array or object
     * @param array   $arguments         An array of arguments to pass if the item is an object method
     * @param string  $type              The type of attribute (@see Twig_TemplateInterface)
     * @param Boolean $isDefinedTest     Whether this is only a defined check
     * @param Boolean $ignoreStrictCheck Whether to ignore the strict attribute check or not
     *
     * @return mixed The attribute value, or a Boolean when $isDefinedTest is true, or null when the attribute is not set and $ignoreStrictCheck is true
     *
     * @throws Twig_Error_Runtime if the attribute does not exist and Twig is running in strict mode and $isDefinedTest is false
     */
    protected function getAttribute($object, $item, array $arguments = array(), $type = Twig_TemplateInterface::ANY_CALL, $isDefinedTest = false, $ignoreStrictCheck = false)
    {
        $item = ctype_digit((string) $item) ? (int) $item : (string) $item;
        // array
        if (Twig_TemplateInterface::METHOD_CALL !== $type) {
            if ((is_array($object) && array_key_exists($item, $object))
                || ($object instanceof ArrayAccess && isset($object[$item]))
            ) {
                if ($isDefinedTest) {
                    return true;
                }
                return $object[$item];
            }
            if (Twig_TemplateInterface::ARRAY_CALL === $type) {
                if ($isDefinedTest) {
                    return false;
                }
                if ($ignoreStrictCheck || !$this->env->isStrictVariables()) {
                    return null;
                }
                if (is_object($object)) {
                    throw new Twig_Error_Runtime(sprintf('Key "%s" in object (with ArrayAccess) of type "%s" does not exist', $item, get_class($object)));
                } elseif (is_array($object)) {
                    throw new Twig_Error_Runtime(sprintf('Key "%s" for array with keys "%s" does not exist', $item, implode(', ', array_keys($object))));
                } else {
                    throw new Twig_Error_Runtime(sprintf('Impossible to access a key ("%s") on a "%s" variable', $item, gettype($object)));
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
            throw new Twig_Error_Runtime(sprintf('Item "%s" for "%s" does not exist', $item, is_array($object) ? 'Array' : $object));
        }
        $class = get_class($object);
        // object property
        if (Twig_TemplateInterface::METHOD_CALL !== $type) {
            if (isset($object->$item) || array_key_exists($item, $object)) {
                if ($isDefinedTest) {
                    return true;
                }
                if ($this->env->hasExtension('sandbox')) {
                    $this->env->getExtension('sandbox')->checkPropertyAllowed($object, $item);
                }
                return $object->$item;
            }
        }
        // object method
        if (!isset(self::$cache[$class]['methods'])) {
            self::$cache[$class]['methods'] = array_change_key_case(array_flip(get_class_methods($object)));
        }
        $lcItem = strtolower($item);
        if (isset(self::$cache[$class]['methods'][$lcItem])) {
            $method = $item;
        } elseif (isset(self::$cache[$class]['methods']['get'.$lcItem])) {
            $method = 'get'.$item;
        } elseif (isset(self::$cache[$class]['methods']['is'.$lcItem])) {
            $method = 'is'.$item;
        } elseif (isset(self::$cache[$class]['methods']['__call'])) {
            $method = $item;
        } else {
            if ($isDefinedTest) {
                return false;
            }
            if ($ignoreStrictCheck || !$this->env->isStrictVariables()) {
                return null;
            }
            throw new Twig_Error_Runtime(sprintf('Method "%s" for object "%s" does not exist', $item, get_class($object)));
        }
        if ($isDefinedTest) {
            return true;
        }
        if ($this->env->hasExtension('sandbox')) {
            $this->env->getExtension('sandbox')->checkMethodAllowed($object, $method);
        }
        $ret = call_user_func_array(array($object, $method), $arguments);
        // useful when calling a template method from a template
        // this is not supported but unfortunately heavily used in the Symfony profiler
        if ($object instanceof Twig_TemplateInterface) {
            return $ret === '' ? '' : new Twig_Markup($ret, $this->env->getCharset());
        }
        return $ret;
    }
    /**
     * This method is only useful when testing Twig. Do not use it.
     */
    static public function clearCache()
    {
        self::$cache = array();
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a Token.
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
class Twig_Token
{
    protected $value;
    protected $type;
    protected $lineno;
    const EOF_TYPE                  = -1;
    const TEXT_TYPE                 = 0;
    const BLOCK_START_TYPE          = 1;
    const VAR_START_TYPE            = 2;
    const BLOCK_END_TYPE            = 3;
    const VAR_END_TYPE              = 4;
    const NAME_TYPE                 = 5;
    const NUMBER_TYPE               = 6;
    const STRING_TYPE               = 7;
    const OPERATOR_TYPE             = 8;
    const PUNCTUATION_TYPE          = 9;
    const INTERPOLATION_START_TYPE  = 10;
    const INTERPOLATION_END_TYPE    = 11;
    /**
     * Constructor.
     *
     * @param integer $type   The type of the token
     * @param string  $value  The token value
     * @param integer $lineno The line position in the source
     */
    public function __construct($type, $value, $lineno)
    {
        $this->type   = $type;
        $this->value  = $value;
        $this->lineno = $lineno;
    }
    /**
     * Returns a string representation of the token.
     *
     * @return string A string representation of the token
     */
    public function __toString()
    {
        return sprintf('%s(%s)', self::typeToString($this->type, true, $this->lineno), $this->value);
    }
    /**
     * Tests the current token for a type and/or a value.
     *
     * Parameters may be:
     * * just type
     * * type and value (or array of possible values)
     * * just value (or array of possible values) (NAME_TYPE is used as type)
     *
     * @param array|integer     $type   The type to test
     * @param array|string|null $values The token value
     *
     * @return Boolean
     */
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
    /**
     * Gets the line.
     *
     * @return integer The source line
     */
    public function getLine()
    {
        return $this->lineno;
    }
    /**
     * Gets the token type.
     *
     * @return integer The token type
     */
    public function getType()
    {
        return $this->type;
    }
    /**
     * Gets the token value.
     *
     * @return string The token value
     */
    public function getValue()
    {
        return $this->value;
    }
    /**
     * Returns the constant representation (internal) of a given type.
     *
     * @param integer $type  The type as an integer
     * @param Boolean $short Whether to return a short representation or not
     * @param integer $line  The code line
     *
     * @return string The string representation
     */
    static public function typeToString($type, $short = false, $line = -1)
    {
        switch ($type) {
            case self::EOF_TYPE:
                $name = 'EOF_TYPE';
                break;
            case self::TEXT_TYPE:
                $name = 'TEXT_TYPE';
                break;
            case self::BLOCK_START_TYPE:
                $name = 'BLOCK_START_TYPE';
                break;
            case self::VAR_START_TYPE:
                $name = 'VAR_START_TYPE';
                break;
            case self::BLOCK_END_TYPE:
                $name = 'BLOCK_END_TYPE';
                break;
            case self::VAR_END_TYPE:
                $name = 'VAR_END_TYPE';
                break;
            case self::NAME_TYPE:
                $name = 'NAME_TYPE';
                break;
            case self::NUMBER_TYPE:
                $name = 'NUMBER_TYPE';
                break;
            case self::STRING_TYPE:
                $name = 'STRING_TYPE';
                break;
            case self::OPERATOR_TYPE:
                $name = 'OPERATOR_TYPE';
                break;
            case self::PUNCTUATION_TYPE:
                $name = 'PUNCTUATION_TYPE';
                break;
            case self::INTERPOLATION_START_TYPE:
                $name = 'INTERPOLATION_START_TYPE';
                break;
            case self::INTERPOLATION_END_TYPE:
                $name = 'INTERPOLATION_END_TYPE';
                break;
            default:
                throw new Twig_Error_Syntax(sprintf('Token of type "%s" does not exist.', $type), $line);
        }
        return $short ? $name : 'Twig_Token::'.$name;
    }
    /**
     * Returns the english representation of a given type.
     *
     * @param integer $type The type as an integer
     * @param integer $line The code line
     *
     * @return string The string representation
     */
    static public function typeToEnglish($type, $line = -1)
    {
        switch ($type) {
            case self::EOF_TYPE:
                return 'end of template';
            case self::TEXT_TYPE:
                return 'text';
            case self::BLOCK_START_TYPE:
                return 'begin of statement block';
            case self::VAR_START_TYPE:
                return 'begin of print statement';
            case self::BLOCK_END_TYPE:
                return 'end of statement block';
            case self::VAR_END_TYPE:
                return 'end of print statement';
            case self::NAME_TYPE:
                return 'name';
            case self::NUMBER_TYPE:
                return 'number';
            case self::STRING_TYPE:
                return 'string';
            case self::OPERATOR_TYPE:
                return 'operator';
            case self::PUNCTUATION_TYPE:
                return 'punctuation';
            case self::INTERPOLATION_START_TYPE:
                return 'begin of string interpolation';
            case self::INTERPOLATION_END_TYPE:
                return 'end of string interpolation';
            default:
                throw new Twig_Error_Syntax(sprintf('Token of type "%s" does not exist.', $type), $line);
        }
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Interface implemented by token parsers.
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
interface Twig_TokenParserInterface
{
    /**
     * Sets the parser associated with this token parser
     *
     * @param $parser A Twig_Parser instance
     */
    function setParser(Twig_Parser $parser);
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    function parse(Twig_Token $token);
    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    function getTag();
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Base class for all token parsers.
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
abstract class Twig_TokenParser implements Twig_TokenParserInterface
{
    /**
     * @var Twig_Parser
     */
    protected $parser;
    /**
     * Sets the parser associated with this token parser
     *
     * @param $parser A Twig_Parser instance
     */
    public function setParser(Twig_Parser $parser)
    {
        $this->parser = $parser;
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Marks a section of a template to be escaped or not.
 *
 * <pre>
 * {% autoescape true %}
 *   Everything will be automatically escaped in this block
 * {% endautoescape %}
 *
 * {% autoescape false %}
 *   Everything will be outputed as is in this block
 * {% endautoescape %}
 *
 * {% autoescape true js %}
 *   Everything will be automatically escaped in this block
 *   using the js escaping strategy
 * {% endautoescape %}
 * </pre>
 */
class Twig_TokenParser_AutoEscape extends Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(Twig_Token $token)
    {
        $lineno = $token->getLine();
        if ($this->parser->getStream()->test(Twig_Token::BLOCK_END_TYPE)) {
            $value = 'html';
        } else {
            $expr = $this->parser->getExpressionParser()->parseExpression();
            if (!$expr instanceof Twig_Node_Expression_Constant) {
                throw new Twig_Error_Syntax('An escaping strategy must be a string or a Boolean.', $lineno);
            }
            $value = $expr->getAttribute('value');
            $compat = true === $value || false === $value;
            if (true === $value) {
                $value = 'html';
            }
            if ($compat && $this->parser->getStream()->test(Twig_Token::NAME_TYPE)) {
                if (false === $value) {
                    throw new Twig_Error_Syntax('Unexpected escaping strategy as you set autoescaping to false.', $lineno);
                }
                $value = $this->parser->getStream()->next()->getValue();
            }
        }
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decideBlockEnd'), true);
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        return new Twig_Node_AutoEscape($value, $body, $lineno, $this->getTag());
    }
    public function decideBlockEnd(Twig_Token $token)
    {
        return $token->test('endautoescape');
    }
    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'autoescape';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Marks a section of a template as being reusable.
 *
 * <pre>
 *  {% block head %}
 *    <link rel="stylesheet" href="style.css" />
 *    <title>{% block title %}{% endblock %} - My Webpage</title>
 *  {% endblock %}
 * </pre>
 */
class Twig_TokenParser_Block extends Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(Twig_Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        $name = $stream->expect(Twig_Token::NAME_TYPE)->getValue();
        if ($this->parser->hasBlock($name)) {
            throw new Twig_Error_Syntax(sprintf("The block '$name' has already been defined line %d", $this->parser->getBlock($name)->getLine()), $lineno);
        }
        $this->parser->setBlock($name, $block = new Twig_Node_Block($name, new Twig_Node(array()), $lineno));
        $this->parser->pushLocalScope();
        $this->parser->pushBlockStack($name);
        if ($stream->test(Twig_Token::BLOCK_END_TYPE)) {
            $stream->next();
            $body = $this->parser->subparse(array($this, 'decideBlockEnd'), true);
            if ($stream->test(Twig_Token::NAME_TYPE)) {
                $value = $stream->next()->getValue();
                if ($value != $name) {
                    throw new Twig_Error_Syntax(sprintf("Expected endblock for block '$name' (but %s given)", $value), $lineno);
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
    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'block';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Evaluates an expression, disgarding the returned value.
 */
class Twig_TokenParser_Do extends Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(Twig_Token $token)
    {
        $expr = $this->parser->getExpressionParser()->parseExpression();
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        return new Twig_Node_Do($expr, $token->getLine(), $this->getTag());
    }
    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'do';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Includes a template.
 *
 * <pre>
 *   {% include 'header.html' %}
 *     Body
 *   {% include 'footer.html' %}
 * </pre>
 */
class Twig_TokenParser_Include extends Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
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
        if ($stream->test(Twig_Token::NAME_TYPE, 'ignore')) {
            $stream->next();
            $stream->expect(Twig_Token::NAME_TYPE, 'missing');
            $ignoreMissing = true;
        }
        $variables = null;
        if ($stream->test(Twig_Token::NAME_TYPE, 'with')) {
            $stream->next();
            $variables = $this->parser->getExpressionParser()->parseExpression();
        }
        $only = false;
        if ($stream->test(Twig_Token::NAME_TYPE, 'only')) {
            $stream->next();
            $only = true;
        }
        $stream->expect(Twig_Token::BLOCK_END_TYPE);
        return array($variables, $only, $ignoreMissing);
    }
    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'include';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2012 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Embeds a template.
 */
class Twig_TokenParser_Embed extends Twig_TokenParser_Include
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(Twig_Token $token)
    {
        $stream = $this->parser->getStream();
        $parent = $this->parser->getExpressionParser()->parseExpression();
        list($variables, $only, $ignoreMissing) = $this->parseArguments();
        // inject a fake parent to make the parent() function work
        $stream->injectTokens(array(
            new Twig_Token(Twig_Token::BLOCK_START_TYPE, '', $token->getLine()),
            new Twig_Token(Twig_Token::NAME_TYPE, 'extends', $token->getLine()),
            new Twig_Token(Twig_Token::STRING_TYPE, '__parent__', $token->getLine()),
            new Twig_Token(Twig_Token::BLOCK_END_TYPE, '', $token->getLine()),
        ));
        $module = $this->parser->parse($stream, array($this, 'decideBlockEnd'), true);
        // override the parent with the correct one
        $module->setNode('parent', $parent);
        $this->parser->embedTemplate($module);
        $stream->expect(Twig_Token::BLOCK_END_TYPE);
        return new Twig_Node_Embed($module->getAttribute('filename'), $module->getAttribute('index'), $variables, $only, $ignoreMissing, $token->getLine(), $this->getTag());
    }
    public function decideBlockEnd(Twig_Token $token)
    {
        return $token->test('endembed');
    }
    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'embed';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Extends a template by another one.
 *
 * <pre>
 *  {% extends "base.html" %}
 * </pre>
 */
class Twig_TokenParser_Extends extends Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(Twig_Token $token)
    {
        if (!$this->parser->isMainScope()) {
            throw new Twig_Error_Syntax('Cannot extend from a block', $token->getLine());
        }
        if (null !== $this->parser->getParent()) {
            throw new Twig_Error_Syntax('Multiple extends tags are forbidden', $token->getLine());
        }
        $this->parser->setParent($this->parser->getExpressionParser()->parseExpression());
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        return null;
    }
    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'extends';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Filters a section of a template by applying filters.
 *
 * <pre>
 * {% filter upper %}
 *  This text becomes uppercase
 * {% endfilter %}
 * </pre>
 */
class Twig_TokenParser_Filter extends Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(Twig_Token $token)
    {
        $name = $this->parser->getVarName();
        $ref = new Twig_Node_Expression_BlockReference(new Twig_Node_Expression_Constant($name, $token->getLine()), true, $token->getLine(), $this->getTag());
        $filter = $this->parser->getExpressionParser()->parseFilterExpressionRaw($ref, $this->getTag());
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decideBlockEnd'), true);
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        $block = new Twig_Node_Block($name, $body, $token->getLine());
        $this->parser->setBlock($name, $block);
        return new Twig_Node_Print($filter, $token->getLine(), $this->getTag());
    }
    public function decideBlockEnd(Twig_Token $token)
    {
        return $token->test('endfilter');
    }
    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'filter';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Flushes the output to the client.
 *
 * @see flush()
 */
class Twig_TokenParser_Flush extends Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(Twig_Token $token)
    {
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        return new Twig_Node_Flush($token->getLine(), $this->getTag());
    }
    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'flush';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Loops over each item of a sequence.
 *
 * <pre>
 * <ul>
 *  {% for user in users %}
 *    <li>{{ user.username|e }}</li>
 *  {% endfor %}
 * </ul>
 * </pre>
 */
class Twig_TokenParser_For extends Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(Twig_Token $token)
    {
        $lineno = $token->getLine();
        $targets = $this->parser->getExpressionParser()->parseAssignmentExpression();
        $this->parser->getStream()->expect(Twig_Token::OPERATOR_TYPE, 'in');
        $seq = $this->parser->getExpressionParser()->parseExpression();
        $ifexpr = null;
        if ($this->parser->getStream()->test(Twig_Token::NAME_TYPE, 'if')) {
            $this->parser->getStream()->next();
            $ifexpr = $this->parser->getExpressionParser()->parseExpression();
        }
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decideForFork'));
        if ($this->parser->getStream()->next()->getValue() == 'else') {
            $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
            $else = $this->parser->subparse(array($this, 'decideForEnd'), true);
        } else {
            $else = null;
        }
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
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
        return new Twig_Node_For($keyTarget, $valueTarget, $seq, $ifexpr, $body, $else, $lineno, $this->getTag());
    }
    public function decideForFork(Twig_Token $token)
    {
        return $token->test(array('else', 'endfor'));
    }
    public function decideForEnd(Twig_Token $token)
    {
        return $token->test('endfor');
    }
    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'for';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Imports macros.
 *
 * <pre>
 *   {% from 'forms.html' import forms %}
 * </pre>
 */
class Twig_TokenParser_From extends Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
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
            if (!$stream->test(Twig_Token::PUNCTUATION_TYPE, ',')) {
                break;
            }
            $stream->next();
        } while (true);
        $stream->expect(Twig_Token::BLOCK_END_TYPE);
        $node = new Twig_Node_Import($macro, new Twig_Node_Expression_AssignName($this->parser->getVarName(), $token->getLine()), $token->getLine(), $this->getTag());
        foreach ($targets as $name => $alias) {
            $this->parser->addImportedFunction($alias, 'get'.$name, $node->getNode('var'));
        }
        return $node;
    }
    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'from';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Tests a condition.
 *
 * <pre>
 * {% if users %}
 *  <ul>
 *    {% for user in users %}
 *      <li>{{ user.username|e }}</li>
 *    {% endfor %}
 *  </ul>
 * {% endif %}
 * </pre>
 */
class Twig_TokenParser_If extends Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(Twig_Token $token)
    {
        $lineno = $token->getLine();
        $expr = $this->parser->getExpressionParser()->parseExpression();
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decideIfFork'));
        $tests = array($expr, $body);
        $else = null;
        $end = false;
        while (!$end) {
            switch ($this->parser->getStream()->next()->getValue()) {
                case 'else':
                    $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
                    $else = $this->parser->subparse(array($this, 'decideIfEnd'));
                    break;
                case 'elseif':
                    $expr = $this->parser->getExpressionParser()->parseExpression();
                    $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
                    $body = $this->parser->subparse(array($this, 'decideIfFork'));
                    $tests[] = $expr;
                    $tests[] = $body;
                    break;
                case 'endif':
                    $end = true;
                    break;
                default:
                    throw new Twig_Error_Syntax(sprintf('Unexpected end of template. Twig was looking for the following tags "else", "elseif", or "endif" to close the "if" block started at line %d)', $lineno), -1);
            }
        }
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        return new Twig_Node_If(new Twig_Node($tests), $else, $lineno, $this->getTag());
    }
    public function decideIfFork(Twig_Token $token)
    {
        return $token->test(array('elseif', 'else', 'endif'));
    }
    public function decideIfEnd(Twig_Token $token)
    {
        return $token->test(array('endif'));
    }
    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'if';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Imports macros.
 *
 * <pre>
 *   {% import 'forms.html' as forms %}
 * </pre>
 */
class Twig_TokenParser_Import extends Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(Twig_Token $token)
    {
        $macro = $this->parser->getExpressionParser()->parseExpression();
        $this->parser->getStream()->expect('as');
        $var = new Twig_Node_Expression_AssignName($this->parser->getStream()->expect(Twig_Token::NAME_TYPE)->getValue(), $token->getLine());
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        return new Twig_Node_Import($macro, $var, $token->getLine(), $this->getTag());
    }
    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'import';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Defines a macro.
 *
 * <pre>
 * {% macro input(name, value, type, size) %}
 *    <input type="{{ type|default('text') }}" name="{{ name }}" value="{{ value|e }}" size="{{ size|default(20) }}" />
 * {% endmacro %}
 * </pre>
 */
class Twig_TokenParser_Macro extends Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(Twig_Token $token)
    {
        $lineno = $token->getLine();
        $name = $this->parser->getStream()->expect(Twig_Token::NAME_TYPE)->getValue();
        $arguments = $this->parser->getExpressionParser()->parseArguments();
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        $this->parser->pushLocalScope();
        $body = $this->parser->subparse(array($this, 'decideBlockEnd'), true);
        if ($this->parser->getStream()->test(Twig_Token::NAME_TYPE)) {
            $value = $this->parser->getStream()->next()->getValue();
            if ($value != $name) {
                throw new Twig_Error_Syntax(sprintf("Expected endmacro for macro '$name' (but %s given)", $value), $lineno);
            }
        }
        $this->parser->popLocalScope();
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        $this->parser->setMacro($name, new Twig_Node_Macro($name, new Twig_Node_Body(array($body)), $arguments, $lineno, $this->getTag()));
        return null;
    }
    public function decideBlockEnd(Twig_Token $token)
    {
        return $token->test('endmacro');
    }
    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'macro';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Marks a section of a template as untrusted code that must be evaluated in the sandbox mode.
 *
 * <pre>
 * {% sandbox %}
 *     {% include 'user.html' %}
 * {% endsandbox %}
 * </pre>
 *
 * @see http://www.twig-project.org/doc/api.html#sandbox-extension for details
 */
class Twig_TokenParser_Sandbox extends Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(Twig_Token $token)
    {
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decideBlockEnd'), true);
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        return new Twig_Node_Sandbox($body, $token->getLine(), $this->getTag());
    }
    public function decideBlockEnd(Twig_Token $token)
    {
        return $token->test('endsandbox');
    }
    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'sandbox';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Defines a variable.
 *
 * <pre>
 *  {% set foo = 'foo' %}
 *
 *  {% set foo = [1, 2] %}
 *
 *  {% set foo = {'foo': 'bar'} %}
 *
 *  {% set foo = 'foo' ~ 'bar' %}
 *
 *  {% set foo, bar = 'foo', 'bar' %}
 *
 *  {% set foo %}Some content{% endset %}
 * </pre>
 */
class Twig_TokenParser_Set extends Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(Twig_Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        $names = $this->parser->getExpressionParser()->parseAssignmentExpression();
        $capture = false;
        if ($stream->test(Twig_Token::OPERATOR_TYPE, '=')) {
            $stream->next();
            $values = $this->parser->getExpressionParser()->parseMultitargetExpression();
            $stream->expect(Twig_Token::BLOCK_END_TYPE);
            if (count($names) !== count($values)) {
                throw new Twig_Error_Syntax("When using set, you must have the same number of variables and assignements.", $lineno);
            }
        } else {
            $capture = true;
            if (count($names) > 1) {
                throw new Twig_Error_Syntax("When using set with a block, you cannot have a multi-target.", $lineno);
            }
            $stream->expect(Twig_Token::BLOCK_END_TYPE);
            $values = $this->parser->subparse(array($this, 'decideBlockEnd'), true);
            $stream->expect(Twig_Token::BLOCK_END_TYPE);
        }
        return new Twig_Node_Set($capture, $names, $values, $lineno, $this->getTag());
    }
    public function decideBlockEnd(Twig_Token $token)
    {
        return $token->test('endset');
    }
    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'set';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Remove whitespaces between HTML tags.
 *
 * <pre>
 * {% spaceless %}
 *      <div>
 *          <strong>foo</strong>
 *      </div>
 * {% endspaceless %}
 *
 * {# output will be <div><strong>foo</strong></div> #}
 * </pre>
 */
class Twig_TokenParser_Spaceless extends Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(Twig_Token $token)
    {
        $lineno = $token->getLine();
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decideSpacelessEnd'), true);
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        return new Twig_Node_Spaceless($body, $lineno, $this->getTag());
    }
    public function decideSpacelessEnd(Twig_Token $token)
    {
        return $token->test('endspaceless');
    }
    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'spaceless';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2011 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Imports blocks defined in another template into the current template.
 *
 * <pre>
 * {% extends "base.html" %}
 *
 * {% use "blocks.html" %}
 *
 * {% block title %}{% endblock %}
 * {% block content %}{% endblock %}
 * </pre>
 *
 * @see http://www.twig-project.org/doc/templates.html#horizontal-reuse for details.
 */
class Twig_TokenParser_Use extends Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(Twig_Token $token)
    {
        $template = $this->parser->getExpressionParser()->parseExpression();
        if (!$template instanceof Twig_Node_Expression_Constant) {
            throw new Twig_Error_Syntax('The template references in a "use" statement must be a string.', $token->getLine());
        }
        $stream = $this->parser->getStream();
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
                if (!$stream->test(Twig_Token::PUNCTUATION_TYPE, ',')) {
                    break;
                }
                $stream->next();
            } while (true);
        }
        $stream->expect(Twig_Token::BLOCK_END_TYPE);
        $this->parser->addTrait(new Twig_Node(array('template' => $template, 'targets' => new Twig_Node($targets))));
        return null;
    }
    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'use';
    }
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 * (c) 2010 Arnaud Le Blanc
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Interface implemented by token parser brokers.
 *
 * Token parser brokers allows to implement custom logic in the process of resolving a token parser for a given tag name.
 *
 * @package twig
 * @author  Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
interface Twig_TokenParserBrokerInterface
{
    /**
     * Gets a TokenParser suitable for a tag.
     *
     * @param string $tag A tag name
     *
     * @return null|Twig_TokenParserInterface A Twig_TokenParserInterface or null if no suitable TokenParser was found
     */
    function getTokenParser($tag);
    /**
     * Calls Twig_TokenParserInterface::setParser on all parsers the implementation knows of.
     *
     * @param Twig_ParserInterface $parser A Twig_ParserInterface interface
     */
    function setParser(Twig_ParserInterface $parser);
    /**
     * Gets the Twig_ParserInterface.
     *
     * @return null|Twig_ParserInterface A Twig_ParserInterface instance or null
     */
    function getParser();
}

}

namespace
{

/*
 * This file is part of Twig.
 *
 * (c) 2010 Fabien Potencier
 * (c) 2010 Arnaud Le Blanc
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Default implementation of a token parser broker.
 *
 * @package twig
 * @author  Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class Twig_TokenParserBroker implements Twig_TokenParserBrokerInterface
{
    protected $parser;
    protected $parsers = array();
    protected $brokers = array();
    /**
     * Constructor.
     *
     * @param array|Traversable $parsers A Traversable of Twig_TokenParserInterface instances
     * @param array|Traversable $brokers A Traversable of Twig_TokenParserBrokerInterface instances
     */
    public function __construct($parsers = array(), $brokers = array())
    {
        foreach ($parsers as $parser) {
            if (!$parser instanceof Twig_TokenParserInterface) {
                throw new Twig_Error('$parsers must a an array of Twig_TokenParserInterface');
            }
            $this->parsers[$parser->getTag()] = $parser;
        }
        foreach ($brokers as $broker) {
            if (!$broker instanceof Twig_TokenParserBrokerInterface) {
                throw new Twig_Error('$brokers must a an array of Twig_TokenParserBrokerInterface');
            }
            $this->brokers[] = $broker;
        }
    }
    /**
     * Adds a TokenParser.
     *
     * @param Twig_TokenParserInterface $parser A Twig_TokenParserInterface instance
     */
    public function addTokenParser(Twig_TokenParserInterface $parser)
    {
        $this->parsers[$parser->getTag()] = $parser;
    }
    /**
     * Adds a TokenParserBroker.
     *
     * @param Twig_TokenParserBroker $broker A Twig_TokenParserBroker instance
     */
    public function addTokenParserBroker(Twig_TokenParserBroker $broker)
    {
        $this->brokers[] = $broker;
    }
    /**
     * Gets a suitable TokenParser for a tag.
     *
     * First looks in parsers, then in brokers.
     *
     * @param string $tag A tag name
     *
     * @return null|Twig_TokenParserInterface A Twig_TokenParserInterface or null if no suitable TokenParser was found
     */
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
        return null;
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

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 * (c) 2009 Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/**
 * Represents a token stream.
 *
 * @package twig
 * @author  Fabien Potencier <fabien@symfony.com>
 */
class Twig_TokenStream
{
    protected $tokens;
    protected $current;
    protected $filename;
    /**
     * Constructor.
     *
     * @param array  $tokens   An array of tokens
     * @param string $filename The name of the filename which tokens are associated with
     */
    public function __construct(array $tokens, $filename = null)
    {
        $this->tokens     = $tokens;
        $this->current    = 0;
        $this->filename   = $filename;
    }
    /**
     * Returns a string representation of the token stream.
     *
     * @return string
     */
    public function __toString()
    {
        return implode("\n", $this->tokens);
    }
    public function injectTokens(array $tokens)
    {
        $this->tokens = array_merge(array_slice($this->tokens, 0, $this->current), $tokens, array_slice($this->tokens, $this->current));
    }
    /**
     * Sets the pointer to the next token and returns the old one.
     *
     * @return Twig_Token
     */
    public function next()
    {
        if (!isset($this->tokens[++$this->current])) {
            throw new Twig_Error_Syntax('Unexpected end of template', -1, $this->filename);
        }
        return $this->tokens[$this->current - 1];
    }
    /**
     * Tests a token and returns it or throws a syntax error.
     *
     * @return Twig_Token
     */
    public function expect($type, $value = null, $message = null)
    {
        $token = $this->tokens[$this->current];
        if (!$token->test($type, $value)) {
            $line = $token->getLine();
            throw new Twig_Error_Syntax(sprintf('%sUnexpected token "%s" of value "%s" ("%s" expected%s)',
                $message ? $message.'. ' : '',
                Twig_Token::typeToEnglish($token->getType(), $line), $token->getValue(),
                Twig_Token::typeToEnglish($type, $line), $value ? sprintf(' with value "%s"', $value) : ''),
                $line,
                $this->filename
            );
        }
        $this->next();
        return $token;
    }
    /**
     * Looks at the next token.
     *
     * @param integer $number
     *
     * @return Twig_Token
     */
    public function look($number = 1)
    {
        if (!isset($this->tokens[$this->current + $number])) {
            throw new Twig_Error_Syntax('Unexpected end of template', -1, $this->filename);
        }
        return $this->tokens[$this->current + $number];
    }
    /**
     * Tests the current token
     *
     * @return bool
     */
    public function test($primary, $secondary = null)
    {
        return $this->tokens[$this->current]->test($primary, $secondary);
    }
    /**
     * Checks if end of stream was reached
     *
     * @return bool
     */
    public function isEOF()
    {
        return $this->tokens[$this->current]->getType() === Twig_Token::EOF_TYPE;
    }
    /**
     * Gets the current token
     *
     * @return Twig_Token
     */
    public function getCurrent()
    {
        return $this->tokens[$this->current];
    }
    /**
     * Gets the filename associated with this stream
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }
}

}
 



namespace Symfony\Component\Console
{

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\DialogHelper;


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

    
    public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN')
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

    
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        if (null === $input) {
            $input = new ArgvInput();
        }

        if (null === $output) {
            $output = new ConsoleOutput();
        }

        try {
            $statusCode = $this->doRun($input, $output);
        } catch (\Exception $e) {
            if (!$this->catchExceptions) {
                throw $e;
            }

            if ($output instanceof ConsoleOutputInterface) {
                $this->renderException($e, $output->getErrorOutput());
            } else {
                $this->renderException($e, $output);
            }
            $statusCode = $e->getCode();

            $statusCode = is_numeric($statusCode) && $statusCode ? $statusCode : 1;
        }

        if ($this->autoExit) {
            if ($statusCode > 255) {
                $statusCode = 255;
            }
                        exit($statusCode);
                    }

        return $statusCode;
    }

    
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $name = $this->getCommandName($input);

        if (true === $input->hasParameterOption(array('--ansi'))) {
            $output->setDecorated(true);
        } elseif (true === $input->hasParameterOption(array('--no-ansi'))) {
            $output->setDecorated(false);
        }

        if (true === $input->hasParameterOption(array('--help', '-h'))) {
            if (!$name) {
                $name = 'help';
                $input = new ArrayInput(array('command' => 'help'));
            } else {
                $this->wantHelps = true;
            }
        }

        if (true === $input->hasParameterOption(array('--no-interaction', '-n'))) {
            $input->setInteractive(false);
        }

        if (function_exists('posix_isatty') && $this->getHelperSet()->has('dialog')) {
            $inputStream = $this->getHelperSet()->get('dialog')->getInputStream();
            if (!posix_isatty($inputStream)) {
                $input->setInteractive(false);
            }
        }

        if (true === $input->hasParameterOption(array('--quiet', '-q'))) {
            $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        } elseif (true === $input->hasParameterOption(array('--verbose', '-v'))) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        }

        if (true === $input->hasParameterOption(array('--version', '-V'))) {
            $output->writeln($this->getLongVersion());

            return 0;
        }

        if (!$name) {
            $name = 'list';
            $input = new ArrayInput(array('command' => 'list'));
        }

                $command = $this->find($name);

        $this->runningCommand = $command;
        $statusCode = $command->run($input, $output);
        $this->runningCommand = null;

        return is_numeric($statusCode) ? $statusCode : 0;
    }

    
    public function setHelperSet(HelperSet $helperSet)
    {
        $this->helperSet = $helperSet;
    }

    
    public function getHelperSet()
    {
        return $this->helperSet;
    }

    
    public function getDefinition()
    {
        return $this->definition;
    }

    
    public function getHelp()
    {
        $messages = array(
            $this->getLongVersion(),
            '',
            '<comment>Usage:</comment>',
            sprintf("  [options] command [arguments]\n"),
            '<comment>Options:</comment>',
        );

        foreach ($this->getDefinition()->getOptions() as $option) {
            $messages[] = sprintf('  %-29s %s %s',
                '<info>--'.$option->getName().'</info>',
                $option->getShortcut() ? '<info>-'.$option->getShortcut().'</info>' : '  ',
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
        if ('UNKNOWN' !== $this->getName() && 'UNKNOWN' !== $this->getVersion()) {
            return sprintf('<info>%s</info> version <comment>%s</comment>', $this->getName(), $this->getVersion());
        }

        return '<info>Console Tool</info>';
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
        $allNamespaces = array();
        foreach ($this->getNamespaces() as $n) {
            $allNamespaces[$n] = explode(':', $n);
        }

        $found = array();
        foreach (explode(':', $namespace) as $i => $part) {
            $abbrevs = static::getAbbreviations(array_unique(array_values(array_filter(array_map(function ($p) use ($i) { return isset($p[$i]) ? $p[$i] : ''; }, $allNamespaces)))));

            if (!isset($abbrevs[$part])) {
                $message = sprintf('There are no commands defined in the "%s" namespace.', $namespace);

                if (1 <= $i) {
                    $part = implode(':', $found).':'.$part;
                }

                if ($alternatives = $this->findAlternativeNamespace($part, $abbrevs)) {
                    if (1 == count($alternatives)) {
                        $message .= "\n\nDid you mean this?\n    ";
                    } else {
                        $message .= "\n\nDid you mean one of these?\n    ";
                    }

                    $message .= implode("\n    ", $alternatives);
                }

                throw new \InvalidArgumentException($message);
            }

            if (count($abbrevs[$part]) > 1) {
                throw new \InvalidArgumentException(sprintf('The namespace "%s" is ambiguous (%s).', $namespace, $this->getAbbreviationSuggestions($abbrevs[$part])));
            }

            $found[] = $abbrevs[$part][0];
        }

        return implode(':', $found);
    }

    
    public function find($name)
    {
                $namespace = '';
        $searchName = $name;
        if (false !== $pos = strrpos($name, ':')) {
            $namespace = $this->findNamespace(substr($name, 0, $pos));
            $searchName = $namespace.substr($name, $pos);
        }

                $commands = array();
        foreach ($this->commands as $command) {
            if ($this->extractNamespace($command->getName()) == $namespace) {
                $commands[] = $command->getName();
            }
        }

        $abbrevs = static::getAbbreviations(array_unique($commands));
        if (isset($abbrevs[$searchName]) && 1 == count($abbrevs[$searchName])) {
            return $this->get($abbrevs[$searchName][0]);
        }

        if (isset($abbrevs[$searchName]) && count($abbrevs[$searchName]) > 1) {
            $suggestions = $this->getAbbreviationSuggestions($abbrevs[$searchName]);

            throw new \InvalidArgumentException(sprintf('Command "%s" is ambiguous (%s).', $name, $suggestions));
        }

                $aliases = array();
        foreach ($this->commands as $command) {
            foreach ($command->getAliases() as $alias) {
                if ($this->extractNamespace($alias) == $namespace) {
                    $aliases[] = $alias;
                }
            }
        }

        $aliases = static::getAbbreviations(array_unique($aliases));
        if (!isset($aliases[$searchName])) {
            $message = sprintf('Command "%s" is not defined.', $name);

            if ($alternatives = $this->findAlternativeCommands($searchName, $abbrevs)) {
                if (1 == count($alternatives)) {
                    $message .= "\n\nDid you mean this?\n    ";
                } else {
                    $message .= "\n\nDid you mean one of these?\n    ";
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
            if ($namespace === $this->extractNamespace($name, substr_count($namespace, ':') + 1)) {
                $commands[$name] = $command;
            }
        }

        return $commands;
    }

    
    public static function getAbbreviations($names)
    {
        $abbrevs = array();
        foreach ($names as $name) {
            for ($len = strlen($name) - 1; $len > 0; --$len) {
                $abbrev = substr($name, 0, $len);
                if (!isset($abbrevs[$abbrev])) {
                    $abbrevs[$abbrev] = array($name);
                } else {
                    $abbrevs[$abbrev][] = $name;
                }
            }
        }

                foreach ($names as $name) {
            $abbrevs[$name] = array($name);
        }

        return $abbrevs;
    }

    
    public function asText($namespace = null, $raw = false)
    {
        $commands = $namespace ? $this->all($this->findNamespace($namespace)) : $this->commands;

        $width = 0;
        foreach ($commands as $command) {
            $width = strlen($command->getName()) > $width ? strlen($command->getName()) : $width;
        }
        $width += 2;

        if ($raw) {
            $messages = array();
            foreach ($this->sortCommands($commands) as $space => $commands) {
                foreach ($commands as $name => $command) {
                    $messages[] = sprintf("%-${width}s %s", $name, $command->getDescription());
                }
            }

            return implode(PHP_EOL, $messages);
        }

        $messages = array($this->getHelp(), '');
        if ($namespace) {
            $messages[] = sprintf("<comment>Available commands for the \"%s\" namespace:</comment>", $namespace);
        } else {
            $messages[] = '<comment>Available commands:</comment>';
        }

                foreach ($this->sortCommands($commands) as $space => $commands) {
            if (!$namespace && '_global' !== $space) {
                $messages[] = '<comment>'.$space.'</comment>';
            }

            foreach ($commands as $name => $command) {
                $messages[] = sprintf("  <info>%-${width}s</info> %s", $name, $command->getDescription());
            }
        }

        return implode(PHP_EOL, $messages);
    }

    
    public function asXml($namespace = null, $asDom = false)
    {
        $commands = $namespace ? $this->all($this->findNamespace($namespace)) : $this->commands;

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->appendChild($xml = $dom->createElement('symfony'));

        $xml->appendChild($commandsXML = $dom->createElement('commands'));

        if ($namespace) {
            $commandsXML->setAttribute('namespace', $namespace);
        } else {
            $namespacesXML = $dom->createElement('namespaces');
            $xml->appendChild($namespacesXML);
        }

                foreach ($this->sortCommands($commands) as $space => $commands) {
            if (!$namespace) {
                $namespaceArrayXML = $dom->createElement('namespace');
                $namespacesXML->appendChild($namespaceArrayXML);
                $namespaceArrayXML->setAttribute('id', $space);
            }

            foreach ($commands as $name => $command) {
                if ($name !== $command->getName()) {
                    continue;
                }

                if (!$namespace) {
                    $commandXML = $dom->createElement('command');
                    $namespaceArrayXML->appendChild($commandXML);
                    $commandXML->appendChild($dom->createTextNode($name));
                }

                $node = $command->asXml(true)->getElementsByTagName('command')->item(0);
                $node = $dom->importNode($node, true);

                $commandsXML->appendChild($node);
            }
        }

        return $asDom ? $dom : $dom->saveXml();
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
            foreach (preg_split("{\r?\n}", $e->getMessage()) as $line) {
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

            if (OutputInterface::VERBOSITY_VERBOSE === $output->getVerbosity()) {
                $output->writeln('<comment>Exception trace:</comment>');

                                $trace = $e->getTrace();
                array_unshift($trace, array(
                    'function' => '',
                    'file'     => $e->getFile() != null ? $e->getFile() : 'n/a',
                    'line'     => $e->getLine() != null ? $e->getLine() : 'n/a',
                    'args'     => array(),
                ));

                for ($i = 0, $count = count($trace); $i < $count; $i++) {
                    $class = isset($trace[$i]['class']) ? $trace[$i]['class'] : '';
                    $type = isset($trace[$i]['type']) ? $trace[$i]['type'] : '';
                    $function = $trace[$i]['function'];
                    $file = isset($trace[$i]['file']) ? $trace[$i]['file'] : 'n/a';
                    $line = isset($trace[$i]['line']) ? $trace[$i]['line'] : 'n/a';

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
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            if ($ansicon = getenv('ANSICON')) {
                return preg_replace('{^(\d+)x.*$}', '$1', $ansicon);
            }

            exec('mode CON', $execData);
            if (preg_match('{columns:\s*(\d+)}i', $execData[4], $matches)) {
                return $matches[1];
            }
        }

        if (preg_match("{rows.(\d+);.columns.(\d+);}i", $this->getSttyColumns(), $match)) {
            return $match[2];
        }
    }

    
    protected function getTerminalHeight()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            if ($ansicon = getenv('ANSICON')) {
                return preg_replace('{^\d+x\d+ \(\d+x(\d+)\)$}', '$1', trim($ansicon));
            }

            exec('mode CON', $execData);
            if (preg_match('{lines:\s*(\d+)}i', $execData[3], $matches)) {
                return $matches[1];
            }
        }

        if (preg_match("{rows.(\d+);.columns.(\d+);}i", $this->getSttyColumns(), $match)) {
            return $match[1];
        }
    }

    
    protected function getCommandName(InputInterface $input)
    {
        return $input->getFirstArgument();
    }

    
    protected function getDefaultInputDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('command', InputArgument::REQUIRED, 'The command to execute'),

            new InputOption('--help',           '-h', InputOption::VALUE_NONE, 'Display this help message.'),
            new InputOption('--quiet',          '-q', InputOption::VALUE_NONE, 'Do not output any message.'),
            new InputOption('--verbose',        '-v', InputOption::VALUE_NONE, 'Increase verbosity of messages.'),
            new InputOption('--version',        '-V', InputOption::VALUE_NONE, 'Display this application version.'),
            new InputOption('--ansi',           '',   InputOption::VALUE_NONE, 'Force ANSI output.'),
            new InputOption('--no-ansi',        '',   InputOption::VALUE_NONE, 'Disable ANSI output.'),
            new InputOption('--no-interaction', '-n', InputOption::VALUE_NONE, 'Do not ask any interactive question.'),
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
        ));
    }

    
    private function getSttyColumns()
    {
        if (!function_exists('proc_open')) {
            return;
        }

        $descriptorspec = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
        $process = proc_open('stty -a | grep columns', $descriptorspec, $pipes, null, null, array('suppress_errors' => true));
        if (is_resource($process)) {
            $info = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return $info;
        }
    }

    
    private function sortCommands($commands)
    {
        $namespacedCommands = array();
        foreach ($commands as $name => $command) {
            $key = $this->extractNamespace($name, 1);
            if (!$key) {
                $key = '_global';
            }

            $namespacedCommands[$key][$name] = $command;
        }
        ksort($namespacedCommands);

        foreach ($namespacedCommands as &$commands) {
            ksort($commands);
        }

        return $namespacedCommands;
    }

    
    private function getAbbreviationSuggestions($abbrevs)
    {
        return sprintf('%s, %s%s', $abbrevs[0], $abbrevs[1], count($abbrevs) > 2 ? sprintf(' and %d more', count($abbrevs) - 2) : '');
    }

    
    private function extractNamespace($name, $limit = null)
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
    private $code;
    private $synopsis;
    private $helperSet;

    
    public function __construct($name = null)
    {
        $this->definition = new InputDefinition();
        $this->ignoreValidationErrors = false;
        $this->applicationDefinitionMerged = false;
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
            return call_user_func($this->code, $input, $output);
        }

        return $this->execute($input, $output);
    }

    
    public function setCode(\Closure $code)
    {
        $this->code = $code;

        return $this;
    }

    
    private function mergeApplicationDefinition()
    {
        if (null === $this->application || true === $this->applicationDefinitionMerged) {
            return;
        }

        $currentArguments = $this->definition->getArguments();
        $this->definition->setArguments($this->application->getDefinition()->getArguments());
        $this->definition->addArguments($currentArguments);

        $this->definition->addOptions($this->application->getDefinition()->getOptions());

        $this->applicationDefinitionMerged = true;
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

    
    protected function getNativeDefinition()
    {
        return $this->getDefinition();
    }

    
    public function addArgument($name, $mode = null, $description = '', $default = null)
    {
        $this->definition->addArgument(new InputArgument($name, $mode, $description, $default));

        return $this;
    }

    
    public function addOption($name, $shortcut = null, $mode = null, $description = '', $default = null)
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

        $placeholders = array(
            '%command.name%',
            '%command.full_name%'
        );
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
        $messages = array(
            '<comment>Usage:</comment>',
            ' '.$this->getSynopsis(),
            '',
        );

        if ($this->getAliases()) {
            $messages[] = '<comment>Aliases:</comment> <info>'.implode(', ', $this->getAliases()).'</info>';
        }

        $messages[] = $this->getNativeDefinition()->asText();

        if ($help = $this->getProcessedHelp()) {
            $messages[] = '<comment>Help:</comment>';
            $messages[] = ' '.str_replace("\n", "\n ", $help)."\n";
        }

        return implode("\n", $messages);
    }

    
    public function asXml($asDom = false)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->appendChild($commandXML = $dom->createElement('command'));
        $commandXML->setAttribute('id', $this->name);
        $commandXML->setAttribute('name', $this->name);

        $commandXML->appendChild($usageXML = $dom->createElement('usage'));
        $usageXML->appendChild($dom->createTextNode(sprintf($this->getSynopsis(), '')));

        $commandXML->appendChild($descriptionXML = $dom->createElement('description'));
        $descriptionXML->appendChild($dom->createTextNode(str_replace("\n", "\n ", $this->getDescription())));

        $commandXML->appendChild($helpXML = $dom->createElement('help'));
        $helpXML->appendChild($dom->createTextNode(str_replace("\n", "\n ", $this->getProcessedHelp())));

        $commandXML->appendChild($aliasesXML = $dom->createElement('aliases'));
        foreach ($this->getAliases() as $alias) {
            $aliasesXML->appendChild($aliasXML = $dom->createElement('alias'));
            $aliasXML->appendChild($dom->createTextNode($alias));
        }

        $definition = $this->getNativeDefinition()->asXml(true);
        $commandXML->appendChild($dom->importNode($definition->getElementsByTagName('arguments')->item(0), true));
        $commandXML->appendChild($dom->importNode($definition->getElementsByTagName('options')->item(0), true));

        return $asDom ? $dom : $dom->saveXml();
    }

    private function validateName($name)
    {
        if (!preg_match('/^[^\:]+(\:[^\:]+)*$/', $name)) {
            throw new \InvalidArgumentException(sprintf('Command name "%s" is invalid.', $name));
        }
    }
}
}
 



namespace Symfony\Component\Console\Command
{

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Command\Command;


class HelpCommand extends Command
{
    private $command;

    
    protected function configure()
    {
        $this->ignoreValidationErrors();

        $this
            ->setName('help')
            ->setDefinition(array(
                new InputArgument('command_name', InputArgument::OPTIONAL, 'The command name', 'help'),
                new InputOption('xml', null, InputOption::VALUE_NONE, 'To output help as XML'),
            ))
            ->setDescription('Displays help for a command')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command displays help for a given command:

  <info>php %command.full_name% list</info>

You can also output the help as XML by using the <comment>--xml</comment> option:

  <info>php %command.full_name% --xml list</info>
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
            $output->writeln($this->command->asXml(), OutputInterface::OUTPUT_RAW);
        } else {
            $output->writeln($this->command->asText());
        }

        $this->command = null;
    }
}
}
 



namespace Symfony\Component\Console\Command
{

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Command\Command;
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

You can also output the information as XML by using the <comment>--xml</comment> option:

  <info>php %command.full_name% --xml</info>

It's also possible to get raw list of commands (useful for embedding command runner):

  <info>php %command.full_name% --raw</info>
EOF
            )
        ;
    }

    
    protected function getNativeDefinition()
    {
        return $this->createDefinition();
    }

    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('xml')) {
            $output->writeln($this->getApplication()->asXml($input->getArgument('namespace')), OutputInterface::OUTPUT_RAW);
        } else {
            $output->writeln($this->getApplication()->asText($input->getArgument('namespace'), $input->getOption('raw')));
        }
    }

    private function createDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('namespace', InputArgument::OPTIONAL, 'The namespace name'),
            new InputOption('xml', null, InputOption::VALUE_NONE, 'To output help as XML'),
            new InputOption('raw', null, InputOption::VALUE_NONE, 'To output raw command list'),
        ));
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
    
    const FORMAT_PATTERN = '#(\\\\?)<(/?)([a-z][a-z0-9_=;-]+)?>((?:(?!\\\\?<).)*)#is';

    private $decorated;
    private $styles = array();
    private $styleStack;

    
    public static function escape($text)
    {
        return preg_replace('/([^\\\\]?)</is', '$1\\<', $text);
    }

    
    public function __construct($decorated = null, array $styles = array())
    {
        $this->decorated = (Boolean) $decorated;

        $this->setStyle('error', new OutputFormatterStyle('white', 'red'));
        $this->setStyle('info', new OutputFormatterStyle('green'));
        $this->setStyle('comment', new OutputFormatterStyle('yellow'));
        $this->setStyle('question', new OutputFormatterStyle('black', 'cyan'));

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
            throw new \InvalidArgumentException('Undefined style: '.$name);
        }

        return $this->styles[strtolower($name)];
    }

    
    public function format($message)
    {
        $message = preg_replace_callback(self::FORMAT_PATTERN, array($this, 'replaceStyle'), $message);

        return str_replace('\\<', '<', $message);
    }

    
    public function getStyleStack()
    {
        return $this->styleStack;
    }

    
    private function replaceStyle($match)
    {
                if ('\\' === $match[1]) {
            return $this->applyCurrentStyle($match[0]);
        }

        if ('' === $match[3]) {
            if ('/' === $match[2]) {
                                $this->styleStack->pop();

                return $this->applyCurrentStyle($match[4]);
            }

                        return '<>'.$this->applyCurrentStyle($match[4]);
        }

        if (isset($this->styles[strtolower($match[3])])) {
            $style = $this->styles[strtolower($match[3])];
        } else {
            $style = $this->createStyleFromString($match[3]);

            if (false === $style) {
                return $this->applyCurrentStyle($match[0]);
            }
        }

        if ('/' === $match[2]) {
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

            if ('fg' == $match[0]) {
                $style->setForeground($match[1]);
            } elseif ('bg' == $match[0]) {
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
    private static $availableForegroundColors = array(
        'black'     => 30,
        'red'       => 31,
        'green'     => 32,
        'yellow'    => 33,
        'blue'      => 34,
        'magenta'   => 35,
        'cyan'      => 36,
        'white'     => 37
    );
    private static $availableBackgroundColors = array(
        'black'     => 40,
        'red'       => 41,
        'green'     => 42,
        'yellow'    => 43,
        'blue'      => 44,
        'magenta'   => 45,
        'cyan'      => 46,
        'white'     => 47
    );
    private static $availableOptions = array(
        'bold'          => 1,
        'underscore'    => 4,
        'blink'         => 5,
        'reverse'       => 7,
        'conceal'       => 8
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
            throw new \InvalidArgumentException(sprintf(
                'Invalid foreground color specified: "%s". Expected one of (%s)',
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
            throw new \InvalidArgumentException(sprintf(
                'Invalid background color specified: "%s". Expected one of (%s)',
                $color,
                implode(', ', array_keys(static::$availableBackgroundColors))
            ));
        }

        $this->background = static::$availableBackgroundColors[$color];
    }

    
    public function setOption($option)
    {
        if (!isset(static::$availableOptions[$option])) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid option specified: "%s". Expected one of (%s)',
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
            throw new \InvalidArgumentException(sprintf(
                'Invalid option specified: "%s". Expected one of (%s)',
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
}
}
 



namespace Symfony\Component\Console\Helper
{

use Symfony\Component\Console\Output\OutputInterface;


class DialogHelper extends Helper
{
    private $inputStream;

    
    public function ask(OutputInterface $output, $question, $default = null)
    {
        $output->write($question);

        $ret = fgets($this->inputStream ?: STDIN, 4096);
        if (false === $ret) {
            throw new \RuntimeException('Aborted');
        }
        $ret = trim($ret);

        return strlen($ret) > 0 ? $ret : $default;
    }

    
    public function askConfirmation(OutputInterface $output, $question, $default = true)
    {
        $answer = 'z';
        while ($answer && !in_array(strtolower($answer[0]), array('y', 'n'))) {
            $answer = $this->ask($output, $question);
        }

        if (false === $default) {
            return $answer && 'y' == strtolower($answer[0]);
        }

        return !$answer || 'y' == strtolower($answer[0]);
    }

    
    public function askAndValidate(OutputInterface $output, $question, $validator, $attempts = false, $default = null)
    {
        $error = null;
        while (false === $attempts || $attempts--) {
            if (null !== $error) {
                $output->writeln($this->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
            }

            $value = $this->ask($output, $question, $default);

            try {
                return call_user_func($validator, $value);
            } catch (\Exception $error) {
            }
        }

        throw $error;
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
        return 'dialog';
    }
}
}
 



namespace Symfony\Component\Console\Helper
{

use Symfony\Component\Console\Formatter\OutputFormatter;


class FormatterHelper extends Helper
{
    
    public function formatSection($section, $message, $style = 'info')
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
            $lines[] = sprintf($large ? '  %s  ' : ' %s ', $message);
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

    
    private function strlen($string)
    {
        if (!function_exists('mb_strlen')) {
            return strlen($string);
        }

        if (false === $encoding = mb_detect_encoding($string)) {
            return strlen($string);
        }

        return mb_strlen($string, $encoding);
    }

    
    public function getName()
    {
        return 'formatter';
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
            if ($parseOptions && '' == $token) {
                $this->parseArgument($token);
            } elseif ($parseOptions && '--' == $token) {
                $parseOptions = false;
            } elseif ($parseOptions && 0 === strpos($token, '--')) {
                $this->parseLongOption($token);
            } elseif ($parseOptions && '-' === $token[0]) {
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

        if (false !== $pos = strpos($name, '=')) {
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

        if (null === $value && $option->acceptValue()) {
                                    $next = array_shift($this->parsed);
            if ('-' !== $next[0]) {
                $value = $next;
            } else {
                array_unshift($this->parsed, $next);
            }
        }

        if (null === $value) {
            if ($option->isValueRequired()) {
                throw new \RuntimeException(sprintf('The "--%s" option requires a value.', $name));
            }

            $value = $option->isValueOptional() ? $option->getDefault() : true;
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
            if ($token && '-' === $token[0]) {
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
                    if (false !== $pos = strpos($token, '=')) {
                        return substr($token, $pos + 1);
                    }

                    return array_shift($tokens);
                }
            }
        }

        return $default;
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
            if ($key && '-' === $key[0]) {
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

    
    protected function parse()
    {
        foreach ($this->parameters as $key => $value) {
            if (0 === strpos($key, '--')) {
                $this->addLongOption(substr($key, 2), $value);
            } elseif ('-' === $key[0]) {
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

    
    public function __construct($name, $mode = null, $description = '', $default = null)
    {
        if (null === $mode) {
            $mode = self::OPTIONAL;
        } elseif (!is_int($mode) || $mode > 7 || $mode < 1) {
            throw new \InvalidArgumentException(sprintf('Argument mode "%s" is not valid.', $mode));
        }

        $this->name        = $name;
        $this->mode        = $mode;
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
            throw new \LogicException('Cannot set a default value except for Parameter::OPTIONAL mode.');
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
        $this->arguments          = array();
        $this->requiredCount      = 0;
        $this->hasOptional        = false;
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
        $arguments = is_int($name) ? array_values($this->arguments) : $this->arguments;

        if (!$this->hasArgument($name)) {
            throw new \InvalidArgumentException(sprintf('The "%s" argument does not exist.', $name));
        }

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
        } elseif (isset($this->shortcuts[$option->getShortcut()]) && !$option->equals($this->options[$this->shortcuts[$option->getShortcut()]])) {
            throw new \LogicException(sprintf('An option with shortcut "%s" already exists.', $option->getShortcut()));
        }

        $this->options[$option->getName()] = $option;
        if ($option->getShortcut()) {
            $this->shortcuts[$option->getShortcut()] = $option->getName();
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
            $shortcut = $option->getShortcut() ? sprintf('-%s|', $option->getShortcut()) : '';
            $elements[] = sprintf('['.($option->isValueRequired() ? '%s--%s="..."' : ($option->isValueOptional() ? '%s--%s[="..."]' : '%s--%s')).']', $shortcut, $option->getName());
        }

        foreach ($this->getArguments() as $argument) {
            $elements[] = sprintf($argument->isRequired() ? '%s' : '[%s]', $argument->getName().($argument->isArray() ? '1' : ''));

            if ($argument->isArray()) {
                $elements[] = sprintf('... [%sN]', $argument->getName());
            }
        }

        return implode(' ', $elements);
    }

    
    public function asText()
    {
                $max = 0;
        foreach ($this->getOptions() as $option) {
            $nameLength = strlen($option->getName()) + 2;
            if ($option->getShortcut()) {
                $nameLength += strlen($option->getShortcut()) + 3;
            }

            $max = max($max, $nameLength);
        }
        foreach ($this->getArguments() as $argument) {
            $max = max($max, strlen($argument->getName()));
        }
        ++$max;

        $text = array();

        if ($this->getArguments()) {
            $text[] = '<comment>Arguments:</comment>';
            foreach ($this->getArguments() as $argument) {
                if (null !== $argument->getDefault() && (!is_array($argument->getDefault()) || count($argument->getDefault()))) {
                    $default = sprintf('<comment> (default: %s)</comment>', $this->formatDefaultValue($argument->getDefault()));
                } else {
                    $default = '';
                }

                $description = str_replace("\n", "\n".str_repeat(' ', $max + 2), $argument->getDescription());

                $text[] = sprintf(" <info>%-${max}s</info> %s%s", $argument->getName(), $description, $default);
            }

            $text[] = '';
        }

        if ($this->getOptions()) {
            $text[] = '<comment>Options:</comment>';

            foreach ($this->getOptions() as $option) {
                if ($option->acceptValue() && null !== $option->getDefault() && (!is_array($option->getDefault()) || count($option->getDefault()))) {
                    $default = sprintf('<comment> (default: %s)</comment>', $this->formatDefaultValue($option->getDefault()));
                } else {
                    $default = '';
                }

                $multiple = $option->isArray() ? '<comment> (multiple values allowed)</comment>' : '';
                $description = str_replace("\n", "\n".str_repeat(' ', $max + 2), $option->getDescription());

                $optionMax = $max - strlen($option->getName()) - 2;
                $text[] = sprintf(" <info>%s</info> %-${optionMax}s%s%s%s",
                    '--'.$option->getName(),
                    $option->getShortcut() ? sprintf('(-%s) ', $option->getShortcut()) : '',
                    $description,
                    $default,
                    $multiple
                );
            }

            $text[] = '';
        }

        return implode("\n", $text);
    }

    
    public function asXml($asDom = false)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->appendChild($definitionXML = $dom->createElement('definition'));

        $definitionXML->appendChild($argumentsXML = $dom->createElement('arguments'));
        foreach ($this->getArguments() as $argument) {
            $argumentsXML->appendChild($argumentXML = $dom->createElement('argument'));
            $argumentXML->setAttribute('name', $argument->getName());
            $argumentXML->setAttribute('is_required', $argument->isRequired() ? 1 : 0);
            $argumentXML->setAttribute('is_array', $argument->isArray() ? 1 : 0);
            $argumentXML->appendChild($descriptionXML = $dom->createElement('description'));
            $descriptionXML->appendChild($dom->createTextNode($argument->getDescription()));

            $argumentXML->appendChild($defaultsXML = $dom->createElement('defaults'));
            $defaults = is_array($argument->getDefault()) ? $argument->getDefault() : (is_bool($argument->getDefault()) ? array(var_export($argument->getDefault(), true)) : ($argument->getDefault() ? array($argument->getDefault()) : array()));
            foreach ($defaults as $default) {
                $defaultsXML->appendChild($defaultXML = $dom->createElement('default'));
                $defaultXML->appendChild($dom->createTextNode($default));
            }
        }

        $definitionXML->appendChild($optionsXML = $dom->createElement('options'));
        foreach ($this->getOptions() as $option) {
            $optionsXML->appendChild($optionXML = $dom->createElement('option'));
            $optionXML->setAttribute('name', '--'.$option->getName());
            $optionXML->setAttribute('shortcut', $option->getShortcut() ? '-'.$option->getShortcut() : '');
            $optionXML->setAttribute('accept_value', $option->acceptValue() ? 1 : 0);
            $optionXML->setAttribute('is_value_required', $option->isValueRequired() ? 1 : 0);
            $optionXML->setAttribute('is_multiple', $option->isArray() ? 1 : 0);
            $optionXML->appendChild($descriptionXML = $dom->createElement('description'));
            $descriptionXML->appendChild($dom->createTextNode($option->getDescription()));

            if ($option->acceptValue()) {
                $optionXML->appendChild($defaultsXML = $dom->createElement('defaults'));
                $defaults = is_array($option->getDefault()) ? $option->getDefault() : (is_bool($option->getDefault()) ? array(var_export($option->getDefault(), true)) : ($option->getDefault() ? array($option->getDefault()) : array()));
                foreach ($defaults as $default) {
                    $defaultsXML->appendChild($defaultXML = $dom->createElement('default'));
                    $defaultXML->appendChild($dom->createTextNode($default));
                }
            }
        }

        return $asDom ? $dom : $dom->saveXml();
    }

    private function formatDefaultValue($default)
    {
        if (version_compare(PHP_VERSION, '5.4', '<')) {
            return str_replace('\/', '/', json_encode($default));
        }

        return json_encode($default, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
}
 



namespace Symfony\Component\Console\Input
{


class InputOption
{
    const VALUE_NONE     = 1;
    const VALUE_REQUIRED = 2;
    const VALUE_OPTIONAL = 4;
    const VALUE_IS_ARRAY = 8;

    private $name;
    private $shortcut;
    private $mode;
    private $default;
    private $description;

    
    public function __construct($name, $shortcut = null, $mode = null, $description = '', $default = null)
    {
        if (0 === strpos($name, '--')) {
            $name = substr($name, 2);
        }

        if (empty($name)) {
            throw new \InvalidArgumentException('An option name cannot be empty.');
        }

        if (empty($shortcut)) {
            $shortcut = null;
        }

        if (null !== $shortcut) {
            if ('-' === $shortcut[0]) {
                $shortcut = substr($shortcut, 1);
            }

            if (empty($shortcut)) {
                throw new \InvalidArgumentException('An option shortcut cannot be empty.');
            }
        }

        if (null === $mode) {
            $mode = self::VALUE_NONE;
        } elseif (!is_int($mode) || $mode > 15 || $mode < 1) {
            throw new \InvalidArgumentException(sprintf('Option mode "%s" is not valid.', $mode));
        }

        $this->name        = $name;
        $this->shortcut    = $shortcut;
        $this->mode        = $mode;
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
            throw new \LogicException('Cannot set a default value when using Option::VALUE_NONE mode.');
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
    const REGEX_STRING = '([^ ]+?)(?: |(?<!\\\\)"|(?<!\\\\)\'|$)';
    const REGEX_QUOTED_STRING = '(?:"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\')';

    
    public function __construct($input, InputDefinition $definition = null)
    {
        parent::__construct(array(), $definition);

        $this->setTokens($this->tokenize($input));
    }

    
    private function tokenize($input)
    {
        $input = preg_replace('/(\r\n|\r|\n|\t)/', ' ', $input);

        $tokens = array();
        $length = strlen($input);
        $cursor = 0;
        while ($cursor < $length) {
            if (preg_match('/\s+/A', $input, $match, null, $cursor)) {
            } elseif (preg_match('/([^="\' ]+?)(=?)('.self::REGEX_QUOTED_STRING.'+)/A', $input, $match, null, $cursor)) {
                $tokens[] = $match[1].$match[2].stripcslashes(str_replace(array('"\'', '\'"', '\'\'', '""'), '', substr($match[3], 1, strlen($match[3]) - 2)));
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
    const VERBOSITY_QUIET   = 0;
    const VERBOSITY_NORMAL  = 1;
    const VERBOSITY_VERBOSE = 2;

    const OUTPUT_NORMAL = 0;
    const OUTPUT_RAW = 1;
    const OUTPUT_PLAIN = 2;

    
    public function write($messages, $newline = false, $type = 0);

    
    public function writeln($messages, $type = 0);

    
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

    
    public function __construct($verbosity = self::VERBOSITY_NORMAL, $decorated = null, OutputFormatterInterface $formatter = null)
    {
        $this->verbosity = null === $verbosity ? self::VERBOSITY_NORMAL : $verbosity;
        $this->formatter = null === $formatter ? new OutputFormatter() : $formatter;
        $this->formatter->setDecorated((Boolean) $decorated);
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
        $this->formatter->setDecorated((Boolean) $decorated);
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

    
    public function writeln($messages, $type = 0)
    {
        $this->write($messages, true, $type);
    }

    
    public function write($messages, $newline = false, $type = 0)
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
        if (!is_resource($stream) || 'stream' !== get_resource_type($stream)) {
            throw new \InvalidArgumentException('The StreamOutput class needs a stream as its first argument.');
        }

        $this->stream = $stream;

        if (null === $decorated) {
            $decorated = $this->hasColorSupport($decorated);
        }

        parent::__construct($verbosity, $decorated, $formatter);
    }

    
    public function getStream()
    {
        return $this->stream;
    }

    
    protected function doWrite($message, $newline)
    {
        if (false === @fwrite($this->stream, $message.($newline ? PHP_EOL : ''))) {
                                    throw new \RuntimeException('Unable to write output.');
                    }

        fflush($this->stream);
    }

    
    protected function hasColorSupport()
    {
                if (DIRECTORY_SEPARATOR == '\\') {
            return false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI');
        }

        return function_exists('posix_isatty') && @posix_isatty($this->stream);
            }
}
}
 



namespace Symfony\Component\Console\Output
{

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;


class ConsoleOutput extends StreamOutput implements ConsoleOutputInterface
{
    private $stderr;

    
    public function __construct($verbosity = self::VERBOSITY_NORMAL, $decorated = null, OutputFormatterInterface $formatter = null)
    {
        $outputStream = 'php://stdout';
        if (!$this->hasStdoutSupport()) {
            $outputStream = 'php://output';
        }

        parent::__construct(fopen($outputStream, 'w'), $verbosity, $decorated, $formatter);

        $this->stderr = new StreamOutput(fopen('php://stderr', 'w'), $verbosity, $decorated, $formatter);
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
        return ('OS400' != php_uname('s'));
    }
}
}
 



namespace Symfony\Component\Console\Output
{


class NullOutput extends Output
{
    
    protected function doWrite($message, $newline)
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
    private $prompt;
    private $processIsolation;

    
    public function __construct(Application $application)
    {
        $this->hasReadline = function_exists('readline');
        $this->application = $application;
        $this->history = getenv('HOME').'/.history_'.$application->getName();
        $this->output = new ConsoleOutput();
        $this->prompt = $application->getName().' > ';
        $this->processIsolation = false;
    }

    
    public function run()
    {
        $this->application->setAutoExit(false);
        $this->application->setCatchExceptions(true);

        if ($this->hasReadline) {
            readline_read_history($this->history);
            readline_completion_function(array($this, 'autocompleter'));
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
        return <<<EOF

Welcome to the <info>{$this->application->getName()}</info> shell (<comment>{$this->application->getVersion()}</comment>).

At the prompt, type <comment>help</comment> for some help,
or <comment>list</comment> to get a list of available commands.

To exit the shell, type <comment>^D</comment>.

EOF;
    }

    
    private function autocompleter($text)
    {
        $info = readline_info();
        $text = substr($info['line_buffer'], 0, $info['end']);

        if ($info['point'] !== $info['end']) {
            return true;
        }

                if (false === strpos($text, ' ') || !$text) {
            return array_keys($this->application->all());
        }

                try {
            $command = $this->application->find(substr($text, 0, strpos($text, ' ')));
        } catch (\Exception $e) {
            return true;
        }

        $list = array('--help');
        foreach ($command->getDefinition()->getOptions() as $option) {
            $list[] = '--'.$option->getName();
        }

        return $list;
    }

    
    private function readline()
    {
        if ($this->hasReadline) {
            $line = readline($this->prompt);
        } else {
            $this->output->write($this->prompt);
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
    }
}
}

namespace
{

/*
 * This file is part of Pimple.
 *
 * Copyright (c) 2009 Fabien Potencier
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
/**
 * Pimple main class.
 *
 * @package pimple
 * @author  Fabien Potencier
 */
class Pimple implements ArrayAccess
{
    private $values;
    /**
     * Instantiate the container.
     *
     * Objects and parameters can be passed as argument to the constructor.
     *
     * @param array $values The parameters or objects.
     */
    public function __construct (array $values = array())
    {
        $this->values = $values;
    }
    /**
     * Sets a parameter or an object.
     *
     * Objects must be defined as Closures.
     *
     * Allowing any PHP callable leads to difficult to debug problems
     * as function names (strings) are callable (creating a function with
     * the same a name as an existing parameter would break your container).
     *
     * @param string $id    The unique identifier for the parameter or object
     * @param mixed  $value The value of the parameter or a closure to defined an object
     */
    public function offsetSet($id, $value)
    {
        $this->values[$id] = $value;
    }
    /**
     * Gets a parameter or an object.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return mixed The value of the parameter or an object
     *
     * @throws InvalidArgumentException if the identifier is not defined
     */
    public function offsetGet($id)
    {
        if (!array_key_exists($id, $this->values)) {
            throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }
        $isFactory = is_object($this->values[$id]) && method_exists($this->values[$id], '__invoke');
        return $isFactory ? $this->values[$id]($this) : $this->values[$id];
    }
    /**
     * Checks if a parameter or an object is set.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return Boolean
     */
    public function offsetExists($id)
    {
        return array_key_exists($id, $this->values);
    }
    /**
     * Unsets a parameter or an object.
     *
     * @param string $id The unique identifier for the parameter or object
     */
    public function offsetUnset($id)
    {
        unset($this->values[$id]);
    }
    /**
     * Returns a closure that stores the result of the given closure for
     * uniqueness in the scope of this instance of Pimple.
     *
     * @param Closure $callable A closure to wrap for uniqueness
     *
     * @return Closure The wrapped closure
     */
    public function share(Closure $callable)
    {
        return function ($c) use ($callable) {
            static $object;
            if (null === $object) {
                $object = $callable($c);
            }
            return $object;
        };
    }
    /**
     * Protects a callable from being interpreted as a service.
     *
     * This is useful when you want to store a callable as a parameter.
     *
     * @param Closure $callable A closure to protect from being evaluated
     *
     * @return Closure The protected closure
     */
    public function protect(Closure $callable)
    {
        return function ($c) use ($callable) {
            return $callable;
        };
    }
    /**
     * Gets a parameter or the closure defining an object.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return mixed The value of the parameter or the closure defining an object
     *
     * @throws InvalidArgumentException if the identifier is not defined
     */
    public function raw($id)
    {
        if (!array_key_exists($id, $this->values)) {
            throw new InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }
        return $this->values[$id];
    }
    /**
     * Extends an object definition.
     *
     * Useful when you want to extend an existing object definition,
     * without necessarily loading that object.
     *
     * @param string  $id       The unique identifier for the object
     * @param Closure $callable A closure to extend the original
     *
     * @return Closure The wrapped closure
     *
     * @throws InvalidArgumentException if the identifier is not defined
     */
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
    /**
     * Returns all defined value names.
     *
     * @return array An array of value names
     */
    public function keys()
    {
        return array_keys($this->values);
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

            $output->writeln(sprintf('<comment>Generated "%s"</comment>', $outputFile));
        }

        $output->writeln(sprintf('<info>Finished</info>', $outputFile));

        return 0;
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
