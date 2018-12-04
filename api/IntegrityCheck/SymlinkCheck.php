<?php

/*
 * This file is part of Contao Manager.
 *
 * (c) Contao Association
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerApi\IntegrityCheck;

use Contao\ManagerApi\ApiKernel;
use Contao\ManagerApi\I18n\Translator;
use Crell\ApiProblem\ApiProblem;
use Symfony\Component\Filesystem\Filesystem;

class SymlinkCheck extends AbstractIntegrityCheck
{
    /**
     * @var ApiKernel
     */
    private $kernel;

    /**
     * Constructor.
     *
     * @param ApiKernel  $kernel
     * @param Translator $translator
     */
    public function __construct(ApiKernel $kernel, Translator $translator)
    {
        parent::__construct($translator);

        $this->kernel = $kernel;
    }

    public function run()
    {
        if (($error = $this->canCreateSymlinks()) === null) {
            return null;
        }

        return (new ApiProblem(
            $this->trans('symlink.title'),
            'https://php.net/symlink'
        ))->setDetail($error);
    }

    private function canCreateSymlinks()
    {
        if (!function_exists('symlink')) {
            return '';
        }

        try {
            $filesystem = new Filesystem();
            $tempname = tempnam(sys_get_temp_dir(), '');

            $filesystem->remove($tempname);
            $filesystem->symlink($this->kernel->getProjectDir(), $tempname);
            $filesystem->remove($tempname);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return null;
    }
}
