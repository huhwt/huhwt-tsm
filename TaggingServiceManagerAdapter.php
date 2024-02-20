<?php

/*
 * webtrees - tagging service manager
 *
 * Copyright (C) 2024 huhwt. All rights reserved.
 *
 * webtrees: online genealogy / web based family history software
 * Copyright (C) 2023 webtrees development team.
 *
 * This class serves as an abstract adapter for other modules to handle tags.
 * 
 */

declare(strict_types=1);

namespace HuHwt\WebtreesMods\TaggingServiceManager;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;

use HuHwt\WebtreesMods\TaggingServiceManager\Traits\TSMconfigTrait;
use HuHwt\WebtreesMods\TaggingServiceManager\Traits\TSMtagsActions;

/**
 * Class TaggingServiceManagerAdapter
 * 
 * @author  EW.H <GIT@HuHnetz.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/huhwt/huhwt-tsm/
 */

class TaggingServiceManagerAdapter 
{
    /** All constants and functions related to handling the Tags  */
    use TSMtagsActions;

    use TSMconfigTrait;

     private $huh;

    /**
     * the module's name for accessing the module_settings
     * @var string $Mname
     */
    private string $Mname;


    /**
     * Retrieve all Record-Types
     * @var boolean
     */
    private bool $all_RecTypes;

     /**
     * @var array $tags
     */
    private array $tags;

    private array $Nxrefs;

    private array $txtNotes;

    public function __construct() {
        $this->huh          = json_decode('"\u210D"');

        $this->tags         = $this->get_Tags();

        $this->all_RecTypes = true;

        $this->tagsNone     = I18N::translate('(none)');

        $_TSMclassName      = Session::get('TSMclassName');
        $this->Mname        = $_TSMclassName;

        $tagOptions         = $this->TAGconfigOptions();
        $this->activeTAG    = $tagOptions[(int) $this->getPreferenceNamed($this->getMname(), 'TAG_Option', '0')];

    }

    /**
     * We need the referring class name.
     *
     * @return string
     */
    private function getMname(): string
    {
        return $this->Mname;
    }

    /**
     * Load all notes prefixed by all TAGs
     * 
     * @param   Tree    $tree
     * @return  array                                   // array of all tags regarding informations
     */
    public function getNotes_All(Tree $tree): array
    {
        $Nxrefs             = [];
        $txtNotes           = [];

        $notes              = $this->get_AllNotes($tree)->toArray();        // get all notes

        $TAGoptions         = $this->TAGconfigOptions();                    // get all tag-prefices defined in preferences

        foreach( $TAGoptions as $ito => $_activeTAG) {
            foreach ($notes as $idx => $note) {                                 // store crossreferences for notes and texts
                $_nxref     = $note->xref();
                $_tagTxt    = $note->getNote();
                if (str_starts_with($_tagTxt, $_activeTAG)) {
                    $Nxrefs[$_nxref]      = $_tagTxt;
                    $txtNotes[$_tagTxt]   = $_nxref;
                }
            }
        }

        $stags = $this->get_TreeTags($tree);
        $stags = array_filter($stags, function($v, $k) { return $v != $this->tagsNone; }, ARRAY_FILTER_USE_BOTH);

        $this->Nxrefs       = [];
        $this->txtNotes     = [];
        foreach( $stags as $sxref => $stag) {
            $_tags = explode(';', $stag);
            foreach ($_tags as $_tagTxt) {
                $_nxref = $txtNotes[$_tagTxt];
                if (($this->Nxrefs[$_nxref] ?? '_NIX_') === '_NIX_') {
                    $this->Nxrefs[$_nxref] = $_tagTxt;
                    $this->txtNotes[$_tagTxt] = $_nxref;
                }
            }
        }

        $ret = [];
        $ret['tags']        = $stags;
        $ret['Nxrefs']      = $this->Nxrefs;
        $ret['txtNotes']    = $this->txtNotes;

        return $ret;

    }

}
