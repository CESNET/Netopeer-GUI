<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Finder\Finder;

/**
 * Clear and Warmup the cache.
 *
 * @author Francis Besset <francis.besset@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CacheClearCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('cache:clear')
            ->setDefinition(array(
                new InputOption('no-warmup', '', InputOption::VALUE_NONE, 'Do not warm up the cache'),
                new InputOption('no-optional-warmers', '', InputOption::VALUE_NONE, 'Skip optional cache warmers (faster)'),
            ))
            ->setDescription('Clears the cache')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command clears the application cache for a given environment
and debug mode:

<info>php %command.full_name% --env=dev</info>
<info>php %command.full_name% --env=prod --no-debug</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $realCacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        $oldCacheDir  = $realCacheDir.'_old';
        $filesystem   = $this->getContainer()->get('filesystem');

        if (!is_writable($realCacheDir)) {
            throw new \RuntimeException(sprintf('Unable to write in the "%s" directory', $realCacheDir));
        }

        if ($filesystem->exists($oldCacheDir)) {
            $filesystem->remove($oldCacheDir);
        }

        $kernel = $this->getContainer()->get('kernel');
        $output->writeln(sprintf('Clearing the cache for the <info>%s</info> environment with debug <info>%s</info>', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));
        $this->getContainer()->get('cache_clearer')->clear($realCacheDir);

        if ($input->getOption('no-warmup')) {
            $filesystem->rename($realCacheDir, $oldCacheDir);
        } else {
            // the warmup cache dir name must have the same length than the real one
            // to avoid the many problems in serialized resources files
            $warmupDir = substr($realCacheDir, 0, -1).'_';

            if ($filesystem->exists($warmupDir)) {
                $filesystem->remove($warmupDir);
            }

            $this->warmup($warmupDir, $realCacheDir, !$input->getOption('no-optional-warmers'));

            $filesystem->rename($realCacheDir, $oldCacheDir);
            if (defined('PHP_WINDOWS_VERSION_BUILD')) {
                sleep(1);  // workaround for windows php rename bug
            }
            $filesystem->rename($warmupDir, $realCacheDir);
        }

        $filesystem->remove($oldCacheDir);
    }

    /**
     * @param string $warmupDir
     * @param string $realCacheDir
     * @param bool   $enableOptionalWarmers
     */
    protected function warmup($warmupDir, $realCacheDir, $enableOptionalWarmers = true)
    {
        $this->getContainer()->get('filesystem')->remove($warmupDir);

        // create a temporary kernel
        $realKernel = $this->getContainer()->get('kernel');
        $realKernelClass = get_class($realKernel);
        $namespace = '';
        if (false !== $pos = strrpos($realKernelClass, '\\')) {
            $namespace = substr($realKernelClass, 0, $pos);
            $realKernelClass = substr($realKernelClass, $pos + 1);
        }
        $tempKernel = $this->getTempKernel($realKernel, $namespace, $realKernelClass, $warmupDir);
        $tempKernel->boot();

        // warmup temporary dir
        $warmer = $tempKernel->getContainer()->get('cache_warmer');
        if ($enableOptionalWarmers) {
            $warmer->enableOptionalWarmers();
        }
        $warmer->warmUp($warmupDir);

        // fix references to the Kernel in .meta files
        foreach (Finder::create()->files()->name('*.meta')->in($warmupDir) as $file) {
            file_put_contents($file, preg_replace(
                '/(C\:\d+\:)"'.get_class($tempKernel).'"/',
                sprintf('$1"%s"', $realKernelClass),
                file_get_contents($file)
            ));
        }

        // fix references to cached files with the real cache directory name
        foreach (Finder::create()->files()->in($warmupDir) as $file) {
            $content = str_replace($warmupDir, $realCacheDir, file_get_contents($file));
            file_put_contents($file, $content);
        }

        // fix references to kernel/container related classes
        $search  = $tempKernel->getName().ucfirst($tempKernel->getEnvironment());
        $replace = $realKernel->getName().ucfirst($realKernel->getEnvironment());
        foreach (Finder::create()->files()->name($search.'*')->in($warmupDir) as $file) {
            $content = str_replace($search, $replace, file_get_contents($file));
            file_put_contents(str_replace($search, $replace, $file), $content);
            unlink($file);
        }
    }

    /**
     * @param KernelInterface $parent
     * @param string          $namespace
     * @param string          $parentClass
     * @param string          $warmupDir
     *
     * @return KernelInterface
     */
    protected function getTempKernel(KernelInterface $parent, $namespace, $parentClass, $warmupDir)
    {
        $rootDir = $parent->getRootDir();
        // the temp kernel class name must have the same length than the real one
        // to avoid the many problems in serialized resources files
        $class = substr($parentClass, 0, -1).'_';
        // the temp kernel name must be changed too
        $name = substr($parent->getName(), 0, -1).'_';
        $code = <<<EOF
<?php

namespace $namespace
{
    class $class extends $parentClass
    {
        public function getCacheDir()
        {
            return '$warmupDir';
        }

        public function getName()
        {
            return '$name';
        }

        public function getRootDir()
        {
            return '$rootDir';
        }
    }
}
EOF;
        $this->getContainer()->get('filesystem')->mkdir($warmupDir);
        file_put_contents($file = $warmupDir.'/kernel.tmp', $code);
        require_once $file;
        @unlink($file);
        $class = "$namespace\\$class";

        return new $class($parent->getEnvironment(), $parent->isDebug());
    }
}
