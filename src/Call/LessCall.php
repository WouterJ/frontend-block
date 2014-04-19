<?php

/**
 * This file is part of frontend-block
 *
 * (c) Aaron Scherer <aequasi@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE
 */

namespace Bldr\Block\Frontend\Call;

use Bldr\Call\AbstractCall;
use Less_Parser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 */
class LessCall extends AbstractCall
{
    /**
     * @var Less_Parser $less
     */
    private $less;

    /**
     * {@inheritDoc}
     */
    public function configure()
    {
        $this->setName('less')
            ->setDescription('Compiles the `src` less files')
            ->addOption('src', true, 'Source to watch')
            ->addOption('compress', false, 'Should bldr remove whitespace and comments')
            ->addOption('sourceMap', false, 'Should bldr create a source map')
            ->addOption(
                'sourceMapWriteTo',
                false,
                'Where should bldr write to? If this isn\'t set, it will be written to the compiled file.'
            )
            ->addOption('sourceMapUrl', false, 'Url to use for the sourcemap');
    }

    /**
     * {@inheritDoc}
     */
    public function run()
    {

        $this->less = new Less_Parser($this->getLessOptions());

        $source = $this->getOption('src');

        foreach ($source as $set) {
            if (!array_key_exists('dest', $set)) {
                throw new \Exception("`src` must have a `dest` option");
            }

            $files = $this->findFiles($set);
            $this->compileFiles($files, $set['dest']);
        }
    }

    /**
     * @return array
     * @throws \RuntimeException
     */
    private function getLessOptions()
    {
        $options = [];
        if ($this->getOption('compress') === true) {
            $options['compres'] = true;
        }

        if ($this->hasOption('sourceMap')) {
            $options['sourceMap'] = $this->getOption('sourceMap');
        }

        if ($this->hasOption('sourceMapWriteTo')) {
            $options['sourceMapWriteTo'] = $this->getOption('sourceMapWriteTo');
        }

        if ($this->hasOption('sourceMapUrl')) {
            $options['sourceMapUrl'] = $this->getOption('sourceMapUrl');
        }

        return $options;
    }

    /**
     * @param array $set
     *
     * @return array|\Symfony\Component\Finder\SplFileInfo[]
     * @throws \Exception
     */
    private function findFiles(array $set)
    {
        if (!array_key_exists('files', $set)) {
            throw new \Exception("`src` must have a `files` option");
        }

        $fileSet = [];

        if (!array_key_exists('path', $set)) {
            $set['path'] = getcwd();
        }

        if (!array_key_exists('recursive', $set)) {
            $set['recursive'] = false;
        }

        $paths = is_array($set['path']) ? $set['path'] : [$set['path']];
        $files = is_array($set['files']) ? $set['files'] : [$set['files']];
        foreach ($paths as $path) {
            foreach ($files as $file) {
                $finder = new Finder();
                $finder->files()->in($path)->name($file);
                if (!$set['recursive']) {
                    $finder->depth('== 0');
                }

                $fileSet = $this->appendFileSet($finder, $fileSet);
            }
        }

        return $fileSet;
    }

    /**
     * @param SplFileInfo[] $files
     * @param string        $destination
     */
    private function compileFiles(array $files, $destination)
    {
        $content = '';
        foreach ($files as $file) {
            if ($this->getOutput()->isVerbose()) {
                $this->getOutput()->writeln("Compiling ".$file);
            }
            $this->less->parseFile($file);
        }

        $output = $this->less->getCss();

        if ($this->getOutput()->isVerbose()) {
            $this->getOutput()->writeln("Writing to ".$destination);
        }
        $fs = new Filesystem;
        $fs->mkdir(dirname($destination));
        $fs->dumpFile($destination, $output);
    }

    /**
     * @param Finder $finder
     * @param array  $fileSet
     *
     * @return SplFileInfo[]
     */
    protected function appendFileSet(Finder $finder, array $fileSet)
    {
        foreach ($finder as $file) {
            /** @var SplFileInfo $file */
            $fileSet[] = $file;
        }

        return $fileSet;
    }
}
