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
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;

/**
 * Trait TSMtagsActions - bundling all actions regarding Session::tags
 */
trait TSMtagsActions
{
    /**
     * the Active Tag descriptor
     * @var string $tagsAction
     */
    public string $tagsAction;
    /**
     * the text declaring that there is actually no tag assigned
     */
    public string $tagsNone;
    /**
     * the XREFs transferred from 'cart'
     */
    public array $tagsXREFs;

    /**
     * the active tag descriptor
     * @var string $activeTAG
     */
    public string $activeTAG;

    /**
     * @param Tree $tree
     *
     * @return bool
     */
    private function isTagsEmpty(Tree $tree): bool
    {
        $tags     = Session::get('tags', []);
        $tags     = is_array($tags) ? $tags : [];
        $contents = $tags[$tree->name()] ?? [];
        $isEmpty  = ($contents === []);

        if ( $isEmpty ) {
            $this->clean_TagsActs($tree);
        }

        return $isEmpty;
    }

    /**
     * Get the Xrefs in the tagging service -> we want the complete XREF-structure.
     *
     * @param Tree $tree
     *
     * @return array
     * 
     * There might be Xrefs collected by other ClippingCart-Module. In those cases
     * there is no actions-structure, but only a boolean value. We mock an action ...
     */
    private function getXREFstruct(Tree $tree): array
    {
        $tags       = Session::get('tags', []);
        $xrefs      = $tags[$tree->name()] ?? [];
        $_xrefs     = [];
        
        foreach ($xrefs as $xref => $actions) {
            $_xref = (string)$xref;
            if (is_bool($actions) === false) {
                $_xrefs[$_xref] = $actions;
            }
        }
        return $_xrefs;
    }

    /**
     * @param Tree $tree
     * @param string $xref
     * 
     * @return bool
     */

    public function put_Tags(Tree $tree, string $xref): bool
    {
        $none_action = $this->tagsNone;

        $tags = Session::get('tags');
        $tags = is_array($tags) ? $tags : [];

        $_tree = $tree->name();

        if (($tags[$_tree][$xref] ?? '_NIX_') === '_NIX_') {
            $tags[$_tree][$xref] = $none_action;
            Session::put('tags', $tags);
            return true;
        } else {
            $tagsActs = $tags[$_tree][$xref];
            if (!is_bool($tagsActs)) {
                if (!$this->tagsAction == $none_action) {
                    if (!str_contains($tagsActs, $this->tagsAction)) {
                        if ($tagsActs == $none_action) {
                            $tagsActs = $this->tagsAction;
                        } else {
                            $tagsActs = $tagsActs . ';' . $this->tagsAction;
                        }
                        $tags[$_tree][$xref] = $tagsActs;
                        Session::put('tags', $tags);
                    }
                }
            } else {
                $tags[$_tree][$xref] = $this->tagsAction;
                Session::put('tags', $tags);
            }
            return false;
        }
    }

    private function get_Tags() : array
    {
        // clippings tags is an array in the session specific for each tree
        $tags  = Session::get('tags', []);
        if ( !is_array($tags) ) {
            $tags = [];
            Session::put('tags', $tags);
        }
        return $tags;
    }

    private function get_TreeTags(Tree $tree) : array
    {
        // clippings tags is an array in the session specific for each tree
        $tags    = $this->get_Tags();
        if (!is_array($tags[$tree->name()]))
            $t_tags = [];
        else
            $t_tags = $tags[$tree->name()];
        return $t_tags;
    }

    private function remove_Tags(Tree $tree, $xref) : void
    {
        $tags = Session::get('tags', []);
        unset($tags[$tree->name()][$xref]);
        Session::put('tags', $tags);
    }

    private function clean_Tags(Tree $tree) : array
    {
        $tags = Session::get('tags', []);
        $tags[$tree->name()] = [];
        Session::put('tags', $tags);
        return $tags;
    }

    private function get_TagsActsMap(Tree $tree) : array
    {
        $tagsAct = Session::get('tagsActs', []);
        $tagsacts = array_keys($tagsAct[$tree->name()] ?? []);
        $tagsacts = array_map('strval', $tagsacts);
        return $tagsacts;
    }

    private function get_TagsActs(Tree $tree) : array
    {
        $tagsAct = Session::get('tagsActs', []);
        $tagsacts = $tagsAct[$tree->name()];
        return $tagsacts;
    }

    private function clean_TagsActs(Tree $tree) : array
    {
        $tagsAct = Session::get('tagsActs', []);
        $tagsAct[$tree->name()] = [];
        Session::put('tagsActs', $tagsAct);

        return $tagsAct;
    }

    private function clean_TagsActs_cact(Tree $tree, string $cact) : array
    {
        $tagsAct = Session::get('tagsActs', []);
        unset($tagsAct[$tree->name()][$cact]);
        Session::put('tagsActs', $tagsAct);
        return $tagsAct;
    }

    private function put_TagsActs(Tree $tree, string $action, string $key) : string
    {
        $tagsAct = Session::get('tagsActs', []);
        if (($tagsAct[$tree->name()][$action] ?? false) === false) {
            $tagsAct[$tree->name()][$action] = $key;
            Session::put('tagsActs', $tagsAct);
        }
        return $action;
    }

    /**
     * @param Tree              $tree
     */
    private function count_TagsRecords(Tree $tree) : int
    {
        $tags = Session::get('tags', []);
        $xrefs = $tags[$tree->name()] ?? [];
        return count($xrefs);
    }

    /**
     * @param Tree              $tree
     * @param int               $xrefsCold
     */
    private function count_TagsRecordsStruct(Tree $tree, int $xrefsCold) : string
    {
        $xrefsCstock = $this->count_TagsRecords($tree);                // Count of xrefs actual in stock - updated
        $xrefsCadded = $xrefsCstock - $xrefsCold;
        $xrefsC = [];
        $xrefsC[] = $xrefsCstock;
        $xrefsC[] = $xrefsCadded;
        $xrefsC[] = I18N::translate('Total number of entries: %s', (string) $xrefsCstock);
        $xrefsC[] = I18N::translate('of which new entries: %s', (string) $xrefsCadded);
        $xrefsCjson = json_encode($xrefsC);
        return $xrefsCjson;
    }
}