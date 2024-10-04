<?php

/*
 * webtrees - tagging service manager
 *
 * Copyright (C) 2024 huhwt. All rights reserved.
 *
 * webtrees: online genealogy / web based family history software
 * Copyright (C) 2023 webtrees development team.
 *
 * This module handles the Ajax-Requests of injected TSM-function in huhwt-cce - the Clippings Cart Enhanced.
 * It takes the reported XREF's and performs the tagging operations.
 * 
 */

declare(strict_types=1);

namespace HuHwt\WebtreesMods\TaggingServiceManager;

use Fisharebest\Webtrees\Module\IndividualListModule;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Services\PendingChangesService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use HuHwt\WebtreesMods\TaggingServiceManager\Traits\TSMcartActions;
use HuHwt\WebtreesMods\TaggingServiceManager\Traits\TSMconfigTrait;
use HuHwt\WebtreesMods\TaggingServiceManager\Traits\TSMdatabaseActions;
use HuHwt\WebtreesMods\TaggingServiceManager\Traits\TSMtagsActions;
use HuHwt\WebtreesMods\TaggingServiceManager\Traits\TSMvizActions;


/**
 * Class TaggingServiceManagerModule
 * 
 * @author  EW.H <GIT@HuHnetz.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/huhwt/huhwt-tsm/
 */

 class TaggingServiceManagerModule extends AbstractModule implements RequestHandlerInterface
 {
    /** All constants and functions related to handling the Cart  */
    use TSMcartActions;
    /** All constants and functions related to handling the Tags  */
    use TSMtagsActions;
    /** All constants and functions related to connecting vizualizations  */
    use TSMvizActions;

    use TSMconfigTrait;

    use TSMdatabaseActions;

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

    private array $txtTags;

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
      * Catch the different ClippingsCart-Actions
      *
      * @param ServerRequestInterface $request
      *
      * @return ResponseInterface
      *
      */
     public function handle(ServerRequestInterface $request): ResponseInterface
     {
        // $M_name             = $this->name();
        // $TAGoptions         = $this->TAGconfigOptions();
        // $this->TAGoptions   = $TAGoptions;
        // $this->activeTAG    = $TAGoptions[(int) $this->getPreference('TAG_Option', '0')];

        $action = Validator::queryParams($request)->string('action');

        if ( $action == 'TagsActRemove' ) {
            return response($this->doTagsActRemove($request));
        }

        if ( $action == 'RemoveXREF' ) {
            return response($this->doRemoveXREF($request));
        }

        if ( $action == 'TagsSave' ) {
            return response($this->doTagsSave($request));
        }

        if ( $action == 'redoWindow' ) {
            return response($this->doRedoWindow($request));
        }

        return response('_NIX_');
    }

    /**
     * get the webtrees entities corresponding to xref-ids
     *
     * @param Tree            $tree
     * @param array<string>   $XREFs
     * 
     * @return array
     */
    public function make_GedcomRecords(Tree $tree, array $XREFs): array
    {
        $records = array_map(static function (string $xref) use ($tree): ?GedcomRecord {
            return Registry::gedcomRecordFactory()->make($xref, $tree);
        }, $XREFs);


        return $records;
    }

    /**
     * there is a bunch of search declarations, some of them are empty -> not set ...
     * ... eliminate them
     * 
     * @param string            $p_actSearch
     * 
     * @return string           remaining significant declarations
     */
    private function cleanSearch($p_actSearch) : string
    {
        if ($p_actSearch == '')
            return '';

        $actSearch_x = explode('&', $p_actSearch);

        $actSearch_ = [];
        foreach($actSearch_x  as $search) {
            if ($search > '') {
                $search_x = explode('=', $search);
                if ($search_x[1] > '') {
                    $search_ = $search_x[0] . '=' . $search_x[1];
                    $actSearch_ [] = $search_;
                }
            }
        }

        $actSearch = '&' . implode('&', $actSearch_);

        return $actSearch;
    }

    /**
     * there is a bunch of search declarations, we want them as keyed array
     * 
     * @param string            $p_actSearch
     * 
     * @return array<string,string>         key     search term         value   search parm
     */
    private function getSearch($p_actSearch) : array
    {
        if ($p_actSearch == '')
            return [''];

        $actSearch_x = explode('&', $p_actSearch);

        $actSearch_ = [];
        foreach($actSearch_x  as $search) {
            if ($search > '') {
                $search_x = explode('=', $search);
                if ($search_x[1] > '') {
                    $actSearch_ [$search_x[0]] = $search_x[1];
                }

            }
        }

        return $actSearch_;
    }

    private function doTagsActRemove(ServerRequestInterface $request): string
    {
        $tree = Validator::attributes($request)->tree();

        // the actual page in DataTable
        $tagsAct = (Validator::queryParams($request)->string('tagsact',''));

        if ($tagsAct == $this->tagsNone) {                              // the (none)-Tag may not be removed
            return (string) $this->count_TagsRecords($tree);
        }
        if (str_contains($tagsAct,'|')) {
            $cAct = substr($tagsAct,0,stripos($tagsAct,'|'));
        } else {
            $cAct = $tagsAct;
        }
        // the XREFs
        $xrefs = Validator::queryParams($request)->string('xrefs', '');

        if ($xrefs > '') {
            $XREFs = explode(';', $xrefs);
            $tags = Session::get('tags', []);
            $_tree = $tree->name();
            foreach ($XREFs as $xref) {
                if (($tags[$_tree][$xref] ?? '_NIX_') != '_NIX_') {
                    $xref_action = $tags[$_tree][$xref];
                    $xref_actions = explode(';', $xref_action);
                    $ica = array_search($cAct, $xref_actions);
                    if (!is_bool($ica)) {
                        array_splice($xref_actions, $ica,1);
                        if (count($xref_actions) > 0) {
                            $xref_action = $xref_actions[0];
                            if (count($xref_actions) > 1)
                                $xref_action = implode(';', $xref_actions);
                            $tags[$_tree][$xref] = $xref_action;
                        } else {
                                unset($tags[$_tree][$xref]);
                        }
                    }
                }
            }
            Session::put('tags', $tags);
        }

        if ($tagsAct > '') {
            $this->clean_TagsActs_cact($tree, $tagsAct);
        }

        return (string) $this->count_TagsRecords($tree);

    }

    private function doRedoWindow(ServerRequestInterface $request): string
    {
        $tree = Validator::attributes($request)->tree();

        $this->clean_TagsActs($tree);

        return (string) $this->count_TagsRecords($tree);
    }

    private function doRemoveXREF(ServerRequestInterface $request): string
    {
        $tree = Validator::attributes($request)->tree();

        // the xref to remove
        $xref = (Validator::queryParams($request)->string('xref',''));

        $this->remove_Tags($tree, $xref);

        $this->remove_Cart($tree, $xref);

        return (string) $this->count_TagsRecords($tree);
    }

    private function doTagsSave(ServerRequestInterface $request): string
    {
        $tree = Validator::attributes($request)->tree();

        /**
         * Get the tags from client and session and clean them ...
         * ... we want only significant tags - so remove '(none)' entries
         */

        // the tags from the client
        $ctags = json_decode((Validator::parsedBody($request)->string('xrefs','')),true);
        if ($ctags === null)
            return (string) $this->count_TagsRecords($tree);

        $ctags = array_filter($ctags, function($v, $k) { return $v != $this->tagsNone; }, ARRAY_FILTER_USE_BOTH);
        $ctags_r = array_reverse($ctags, true);
        // the tags in session
        $stags = $this->get_TreeTags($tree);
        $stags = array_filter($stags, function($v, $k) { return $v != $this->tagsNone; }, ARRAY_FILTER_USE_BOTH);

        /**
         * test the tags which are assigned in client as well as in session
         * in the beginning the number of tagged xrefs in client and session is equal ...
         * ... so that there will be a corresponding item for each ...
         * ... but client actions will add tags as well as remove some of them ...
         * ... when we eliminate all correspondings tags on each side ...
         * ... then all remaining client side tagged xrefs will be the to be added ones ...
         * ... and all remaining session side tagged xrefs will be the to be removed ones 
         */

        foreach( $ctags_r as $c_xref => $c_tag) {               // loop over client items - reversed order
            if (($stags[$c_xref] ?? '_NIX_') != '_NIX_') {          // check if there is a corresponding session item
                $s_tag = $stags[$c_xref];
                $c_tags = explode(';', $c_tag);                         // tags reported from client
                $s_tags = explode(';', $s_tag);                         // tags stored in session
                $ic_c = count($c_tags) - 1;
                for( $ic = $ic_c; $ic>=0; $ic--) {                      // loop in reversed order
                    $tag = $c_tags[$ic];
                    if ( in_array($tag, $s_tags)) {                         // tag from client already stored in session ? ...
                        $is = array_search($tag, $s_tags);                      // ... yes - get the index ...
                        array_splice($s_tags, $is, 1);                          // ... and remove it from session-tags
                        array_splice($c_tags, $ic, 1);                          // ... and also from client-tags
                    }
                }
                $ic_c = count($c_tags);                                 // test the client tags
                if ( $ic_c == 0 )                                          // are there any tags in ctags[c_xref]? ...
                    unset($ctags[$c_xref]);                                     // ... no -> remove the entry in ctags - any changed
                else {                                                     // ... yes -> rebuild the internal tags-structure
                    if ( $ic_c == 1)
                        $ctags[$c_xref] = $c_tags[0];
                    else
                        $ctags[$c_xref] = implode(';', $c_tags);
                }

                $is_c = count($s_tags);                                 // test the session tags
                if ( $is_c == 0 )                                          // are there any tags in stags[c_xref]?
                    unset($stags[$c_xref]);                                     // ... no -> remove the entry in stags
                else {                                                     // ... yes -> rebuild the internal tags-structure
                    if ( $is_c == 1)
                        $stags[$c_xref] = $s_tags[0];
                    else
                        $stags[$c_xref] = implode(';', $s_tags);
                }
            }
        }

        /**
         * Check the tags - which tags are newly assigned in client and which are removed?
         */
        $tags_add = [];
        $tags_del = [];
        $do_updTags = false;

        // test the tags in session if there are any remaining
        foreach( $stags as $s_xref => $s_tag) {                             // they have not been referenced by tags from client ...
            if (($tags_del[$s_xref] ?? '_NIX_') == '_NIX_') {
                $tags_del[$s_xref] = explode(';', $s_tag);                      // ... so they have to been deleted! store them
                $do_updTags = true;
            }
        }
        // test the tags from client if there are any remaining
        foreach( $ctags as $c_xref => $c_tag) {                             // they have not been referenced by tags from client ...
            if (($tags_add[$c_xref] ?? '_NIX_') == '_NIX_') {
                $tags_add[$c_xref] = explode(';', $c_tag);                      // ... so they have to been added! store them
                $do_updTags = true;
            }
        }

        /**
         * Update Database
         * by changing from one tag to another there may be various tags - not only such prefixed by the ActiveTag ...
         * ... so we load all variants of tag-prefices
         */
        $this->getNotes_All($tree);

        if( count($tags_add) > 0)
            $this->doUpdate_add($tree, $tags_add);
        if( count($tags_del) > 0)
            $this->doUpdate_del($tree, $tags_del);

        /**
         * Update Session::tags
         */
        $_tree              = $tree->name();
        $none_action         = $this->tagsNone;
        if ($do_updTags) {
            $tags  = Session::get('tags', []);
            foreach( $tags_add as $_xrefC => $_tags ) {
                $tagsActs   = $tags[$_tree][$_xrefC];
                foreach ( $_tags as $tAction ) {
                    if (!str_contains($tagsActs, $tAction)) {
                        if ($tagsActs == $none_action) {
                            $tagsActs = $tAction;
                        } else {
                            $tagsActs = $tagsActs . ';' . $tAction;
                        }
                    }
                }
                $tags[$_tree][$_xrefC] = $tagsActs;
            }
            foreach( $tags_del as $_xrefS => $_tags ) {
                $tagsActs   = $tags[$_tree][$_xrefS];
                $_tagActs   = [];
                if ($tagsActs != $none_action)
                    $_tagActs = explode(';', $tagsActs);
                foreach ( $_tags as $tAction ) {
                    if (($i = array_search($tAction, $_tagActs)) !== FALSE) {
                        unset($_tagActs[$i]);
                    }
                }
                if ( count($_tagActs) > 0 ) {
                    count($_tagActs) == 1 ? $tagsActs = $_tagActs[0] : $tagsActs = implode(';', $_tagActs);
                } else {
                    $tagsActs = $none_action;
                }
                $tags[$_tree][$_xrefS] = $tagsActs;
            }
            Session::put('tags', $tags);
        }

        return (string) $this->count_TagsRecords($tree);
    }

    private function doUpdate_add(Tree $tree, array $tags_add) : void
    {
        $_oref = '';
        $gedcom_old = '';
        $gedcom_new = '';
        $record = null;
        foreach( $tags_add as $_xref => $_tags ) {
            if ($_oref != $_xref) {
                if( $gedcom_old > '' ) {
                    if( $gedcom_old != $gedcom_new ) {
                        $this->doUpdateGedcom($tree, $_oref, $gedcom_old, $gedcom_new, $record);
                        $gedcom_old = '';
                    }
                }
                $record = Registry::gedcomRecordFactory()->make($_xref, $tree);
                // $record = Auth::checkRecordAccess($record, true);
                $gedcom_old = $record->gedcom();
                $gedcom_new = $gedcom_old;
                $_oref = $_xref;
            }
            foreach( $_tags as $_tag) {
                $nref = $this->getNref_for_text($_tag);         // get the XREFid corresponding with TAG-text
                if ( $nref ) {
                    /** this is done in webtrees core to build a note ...
                     *  $keep_chan = true;
                     *  $levels = ['1'];
                     *  $tags   = ['NOTE'];
                     *  $values    = Validator::parsedBody($request)->array('values');       // this has to be like '@N_@'
                     *  $gedcom    = $this->gedcom_edit_service->editLinesToGedcom($record::RECORD_TYPE, $levels, $tags, $values, false); // "1 NOTE @N3@"
                     */
                    $note_struct = '1 NOTE @' . $nref . '@';    // ... and we do it in 1 line ...
                    $gedcom_new .= "\n" . $note_struct;         // ... and concatenate it to the gedcom
                }
            }
        }
        if( $gedcom_old != $gedcom_new ) {
            $this->doUpdateGedcom($tree, $_oref, $gedcom_old, $gedcom_new, $record);
        }

    }

    private function doUpdate_del(Tree $tree, array $tags_del) : void
    {
        $_oref = '';
        $gedcom_old = '';
        $gedcom_new = '';
        $record = null;
        foreach( $tags_del as $_xref => $_tags ) {
            if ($_oref != $_xref) {
                if( $gedcom_old > '' ) {
                    if( $gedcom_old != $gedcom_new ) {
                        $this->doUpdateGedcom($tree, $_oref, $gedcom_old, $gedcom_new, $record);
                        $gedcom_old = '';
                    }
                }
                $record = Registry::gedcomRecordFactory()->make($_xref, $tree);
                // $record = Auth::checkRecordAccess($record, true);
                $gedcom_old = $record->gedcom();
                $gedcom_new = $gedcom_old;
                $_oref = $_xref;
            }
            foreach( $_tags as $_tag) {
                $nref = $this->getNref_for_text($_tag);         // get the XREFid corresponding with TAG-text
                if ( $nref ) {
                    /** this is done in webtrees core to build a note ...
                     *  $keep_chan = true;
                     *  $levels = ['1'];
                     *  $tags   = ['NOTE'];
                     *  $values    = Validator::parsedBody($request)->array('values');       // this has to be like '@N_@'
                     *  $gedcom    = $this->gedcom_edit_service->editLinesToGedcom($record::RECORD_TYPE, $levels, $tags, $values, false); // "1 NOTE @N3@"
                     */
                    $note_struct = '1 NOTE @' . $nref . '@';        // ... and we do it in 1 line ...
                    $ipos = strpos($gedcom_new, $note_struct);      // ... look where in gedcom it is located ...
                    if ( $ipos ) {
                        $nlen = strlen($note_struct);
                        $glen = strlen($gedcom_new);
                        $ged_p1 = substr($gedcom_new, 0, $ipos-1);  // ... and cut it out
                        $ged_p2 = '';
                        $ipos2 = $ipos + $nlen;
                        if ($ipos2 < $glen)
                            $ged_p2 = substr($gedcom_new, $ipos2);
                        $gedcom_new = $ged_p1 . $ged_p2;
                    }
                }
            }
        }
        if( $gedcom_old != $gedcom_new ) {
            $this->doUpdateGedcom($tree, $_oref, $gedcom_old, $gedcom_new, $record);
        }

    }

    private function doUpdateGedcom(Tree $tree, string $xref, string $old_gedcom, string $new_gedcom, GedcomRecord $record) : void
    {
        DB::table('change')->insert([
            'gedcom_id'  => $tree->id(),
            'xref'       => $xref,
            'old_gedcom' => $old_gedcom,
            'new_gedcom' => $new_gedcom,
            'status'     => 'pending',
            'user_id'    => Auth::id(),
        ]);

        $pending_changes_service = Registry::container()->get(PendingChangesService::class);
        assert($pending_changes_service instanceof PendingChangesService);

        $pending_changes_service->acceptRecord($record);

    }

    /**
     * Load all notes prefixed by Active Tag
     */
    private function getNotes(Tree $tree): void 
    {
        $this->Nxrefs       = [];
        $this->txtTags      = [];

        $_activeTAG         = $this->activeTAG . ':';

        $notes              = $this->get_AllNotes($tree)->toArray();        // get all notes

        foreach ($notes as $idx => $note) {                                 // store crossreferences for notes and texts
            $_xref      = $note->xref();
            $_tagTxt    = $note->getNote();
            if (str_starts_with($_tagTxt, $_activeTAG)) {
                $this->Nxrefs[$_xref]       = $_tagTxt;
                $this->txtTags[$_tagTxt]    = $_xref;
            }
        }

    }

    /**
     * Load all notes prefixed by all TAGs
     */
    private function getNotes_All(Tree $tree): void
    {
        $this->Nxrefs       = [];
        $this->txtTags      = [];

        $notes              = $this->get_AllNotes($tree)->toArray();        // get all notes

        $TAGoptions         = $this->TAGconfigOptions();

        foreach( $TAGoptions as $ito => $_activeTAG) {
            foreach ($notes as $idx => $note) {                                 // store crossreferences for notes and texts
                $_xref      = $note->xref();
                $_tagTxt    = $note->getNote();
                if (str_starts_with($_tagTxt, $_activeTAG)) {
                    $this->Nxrefs[$_xref]       = $_tagTxt;
                    $this->txtTags[$_tagTxt]    = $_xref;
                }
            }
        }

    }


    private function getNref_for_text(string $tagTxt) : string
    {
        $Nref = '';
        if (($this->txtTags[$tagTxt] ?? '_NIX_') != '_NIX_') {
            $Nref = $this->txtTags[$tagTxt];
        }
        return $Nref;
    }

}