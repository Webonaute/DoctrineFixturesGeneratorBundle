<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webonaute\DoctrineFixturesGeneratorBundle\Command;

/**
 * Validator functions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Validators
{
    /**
     * Validates that the given namespace (e.g. Acme\FooBundle) is a valid format.
     *
     * If $requireVendorNamespace is true, then we require you to have a vendor
     * namespace (e.g. Acme).
     *
     * @param $namespace
     * @param bool $requireVendorNamespace
     *
     * @return string
     */
    public static function validateBundleNamespace($namespace, $requireVendorNamespace = true)
    {
        if (!preg_match('/Bundle$/', $namespace)) {
            throw new \InvalidArgumentException('The namespace must end with Bundle.');
        }

        $namespace = strtr($namespace, '/', '\\');
        if (!preg_match('/^(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\\\?)+$/', $namespace)) {
            throw new \InvalidArgumentException('The namespace contains invalid characters.');
        }

        // validate reserved keywords
        $reserved = self::getReservedWords();
        foreach (explode('\\', $namespace) as $word) {
            if (in_array(strtolower($word), $reserved)) {
                throw new \InvalidArgumentException(sprintf('The namespace cannot contain PHP reserved words ("%s").', $word));
            }
        }

        // validate that the namespace is at least one level deep
        if ($requireVendorNamespace && false === strpos($namespace, '\\')) {
            $msg = array();
            $msg[] = sprintf('The namespace must contain a vendor namespace (e.g. "VendorName\%s" instead of simply "%s").', $namespace, $namespace);
            $msg[] = 'If you\'ve specified a vendor namespace, did you forget to surround it with quotes (init:bundle "Acme\BlogBundle")?';

            throw new \InvalidArgumentException(implode("\n\n", $msg));
        }

        return $namespace;
    }

    public static function validateBundleName($bundle)
    {
        if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $bundle)) {
            throw new \InvalidArgumentException(sprintf('The bundle name %s contains invalid characters.', $bundle));
        }

        if (!preg_match('/Bundle$/', $bundle)) {
            throw new \InvalidArgumentException('The bundle name must end with Bundle.');
        }

        return $bundle;
    }

    public static function validateControllerName($controller)
    {
        try {
            self::validateEntityName($controller);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The controller name must contain a : ("%s" given, expecting something like AcmeBlogBundle:Post)',
                    $controller
                )
            );
        }

        return $controller;
    }

    public static function validateFormat($format)
    {
        if (!$format) {
            throw new \RuntimeException('Please enter a configuration format.');
        }

        $format = strtolower($format);

        // in case they typed "yaml", but ok with that
        if ($format == 'yaml') {
            $format = 'yml';
        }

        if (!in_array($format, array('php', 'xml', 'yml', 'annotation'))) {
            throw new \RuntimeException(sprintf('Format "%s" is not supported.', $format));
        }

        return $format;
    }

    /**
     * Performs basic checks in entity name.
     *
     * @param string $entity
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public static function validateEntityName($entity)
    {
        if (!preg_match('{^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*:[a-zA-Z0-9_\x7f-\xff\\\/]+$}', $entity)) {
            throw new \InvalidArgumentException(sprintf('The entity name isn\'t valid ("%s" given, expecting something like AcmeBlogBundle:Blog/Post)', $entity));
        }

        return $entity;
    }

    public static function getReservedWords()
    {
        return array(
            'abstract',
            'and',
            'array',
            'as',
            'break',
            'callable',
            'case',
            'catch',
            'class',
            'clone',
            'const',
            'continue',
            'declare',
            'default',
            'do',
            'else',
            'elseif',
            'enddeclare',
            'endfor',
            'endforeach',
            'endif',
            'endswitch',
            'endwhile',
            'extends',
            'final',
            'finally',
            'for',
            'foreach',
            'function',
            'global',
            'goto',
            'if',
            'implements',
            'interface',
            'instanceof',
            'insteadof',
            'namespace',
            'new',
            'or',
            'private',
            'protected',
            'public',
            'static',
            'switch',
            'throw',
            'trait',
            'try',
            'use',
            'var',
            'while',
            'xor',
            'yield',
            '__CLASS__',
            '__DIR__',
            '__FILE__',
            '__LINE__',
            '__FUNCTION__',
            '__METHOD__',
            '__NAMESPACE__',
            '__TRAIT__',
            '__halt_compiler',
            'die',
            'echo',
            'empty',
            'exit',
            'eval',
            'include',
            'include_once',
            'isset',
            'list',
            'require',
            'require_once',
            'return',
            'print',
            'unset',
        );
    }
}