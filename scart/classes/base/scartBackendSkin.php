<?php

namespace abuseio\scart\classes\base;

use Backend\Skins\Standard;

/**
 * Modified backend skin information file.
 *
 * This is modified to include an additional path to override the default layouts.
 *
 */

class scartBackendSkin extends Standard {

    /** {@inheritDoc}
     */
    public function getLayoutPaths()
    {
        return [
            base_path() . '/plugins/abuseio/scart/layouts/scart',
            $this->skinPath.'/layouts'];
    }
}
