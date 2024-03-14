<?php

/**
 * webtrees - tagging service manager
 *
 * Copyright (C) 2024 huhwt. All rights reserved.
 *
 * webtrees: online genealogy / web based family history software
 * Copyright (C) 2023 webtrees development team.
 *
 */

declare(strict_types=1);

namespace HuHwt\WebtreesMods\TaggingServiceManager\Traits;

use Fisharebest\Webtrees\I18N;

/**
 * Trait TSMmodulesTrait - bundling all declarations for TSM-modules
 */
trait TSMmodulesTrait
{

    // List of const for module administration
    public const CUSTOM_TITLE       = 'Tagging service manager';
    public const CUSTOM_DESCRIPTION = 'View and manage Tags for better structuring your Family Tree';
    public const CUSTOM_MODULE      = 'huhwt-tsm';
    public const CUSTOM_AUTHOR      = 'EW.H';
    public const CUSTOM_WEBSITE     = 'https://github.com/huhwt/' . self::CUSTOM_MODULE . '/';
    public const CUSTOM_VERSION     = '2.1.18.2';
    public const CUSTOM_LAST        = 'https://github.com/huhwt/' .
                                        self::CUSTOM_MODULE. '/blob/master/latest-version.txt';

    /**
     * The person or organisation who created this module.
     *
     * @return string
     */
    public function customModuleAuthorName(): string {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * A URL that will provide the latest version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return self::CUSTOM_LAST;
    }

    /**
     * The version of this module.
     *
     * @return string
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * Where to get support for this module?  Perhaps a GitHub repository?
     *
     * @return string
     */
    public function customModuleSupportUrl(): string
    {
        return self::CUSTOM_WEBSITE;
    }

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        /* I18N: Name of a module */
        return $this->huh . ' ' . I18N::translate(self::CUSTOM_TITLE);
    }

    /**
     * How should this module be identified in the menu list?
     *
     * @return string
     */
    protected function menuTitle(): string
    {
        return $this->huh . ' ' . I18N::translate(self::CUSTOM_TITLE);
    }

    /**
     * Where does this module store its resources?
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR .'resources' . DIRECTORY_SEPARATOR;
    }


}