<?php

namespace reportertool\eokm\classes;

use Backend\Skins\Standard;

/**
 * Modified backend skin information file.
 *
 * This is modified to include an additional path to override the default layouts.
 *
 */

class BackendSkin extends Standard
{
    /**
     * {@inheritDoc}
     */
    public function getLayoutPaths()
    {
        return [base_path() . '/plugins/reportertool/eokm/layouts/ert'];
    }
}
