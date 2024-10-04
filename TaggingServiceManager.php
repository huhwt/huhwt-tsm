<?php
/*
 * webtrees - tagging service manager
 *
 * Copyright (C) 2024 huhwt. All rights reserved.
 *
 * webtrees: online genealogy / web based family history software
 * Copyright (C) 2023 webtrees development team.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace HuHwt\WebtreesMods\TaggingServiceManager;

use Aura\Router\Map;
use Aura\Router\Route;
use Aura\Router\RouterContainer;
use Aura\Router\Rule\Allows;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\FamilyPage;
use Fisharebest\Webtrees\Http\RequestHandlers\IndividualPage;
use Fisharebest\Webtrees\Http\RequestHandlers\LocationPage;
use Fisharebest\Webtrees\Http\RequestHandlers\MediaPage;
use Fisharebest\Webtrees\Http\RequestHandlers\NotePage;
use Fisharebest\Webtrees\Http\RequestHandlers\RepositoryPage;
use Fisharebest\Webtrees\Http\RequestHandlers\SourcePage;
use Fisharebest\Webtrees\Http\RequestHandlers\SubmitterPage;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\FamilyListModule;
use Fisharebest\Webtrees\Module\IndividualListModule;
use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Submitter;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Webtrees;
use Illuminate\Support\Collection;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;
use Nyholm\Psr7\Uri;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Fig\Http\Message\RequestMethodInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Module\ModuleMenuTrait;
use Fisharebest\Webtrees\Module\NotesModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\View;
use SebastianBergmann\Type\VoidType;

use HuHwt\WebtreesMods\TaggingServiceManager\ListProcessor;
use HuHwt\WebtreesMods\TaggingServiceManager\TaggingServiceManagerModule;
use HuHwt\WebtreesMods\TaggingServiceManager\Http\RequestHandlers\TSMCreateNoteModal;
use HuHwt\WebtreesMods\TaggingServiceManager\Http\RequestHandlers\TSMCreateNoteAction;
use HuHwt\WebtreesMods\TaggingServiceManager\Http\RequestHandlers\TSMchooseATagModal;
use HuHwt\WebtreesMods\TaggingServiceManager\Http\RequestHandlers\TSMchooseATagAction;

use HuHwt\WebtreesMods\TaggingServiceManager\Traits\TSMmodulesTrait;
use HuHwt\WebtreesMods\TaggingServiceManager\Traits\TSMconfigTrait;
use HuHwt\WebtreesMods\TaggingServiceManager\Traits\TSMdatabaseActions;
use HuHwt\WebtreesMods\TaggingServiceManager\Traits\TSMcartActions;
use HuHwt\WebtreesMods\TaggingServiceManager\Traits\TSMtagsActions;
use HuHwt\WebtreesMods\TaggingServiceManager\Traits\TSMvizActions;

// control functions
use stdClass;
use function array_filter;
use function array_keys;
use function array_map;
use function array_search;
use function assert;
use function count;
use function array_key_exists;
use function fopen;
use function file_put_contents;
use function in_array;
use function is_string;
use function json_encode;
use function preg_match_all;
use function redirect;
use function rewind;
use function route;
use function str_replace;
use function str_starts_with;
use function stream_get_meta_data;
use function tmpfile;
use function uasort;
use function view;

// string functions
use function strtolower;
use function addcslashes;
use const PREG_SET_ORDER;
/**
 * Class TaggingServiceManager
 */
class TaggingServiceManager extends AbstractModule
                         implements ModuleCustomInterface, ModuleConfigInterface
{
    use ModuleConfigTrait;
    use TSMconfigTrait;

    use ModuleCustomTrait;
    /** All constants and functions according to ModuleCustomTrait */
    use TSMmodulesTrait {
        TSMmodulesTrait::customModuleAuthorName insteadof ModuleCustomTrait;
        TSMmodulesTrait::customModuleLatestVersionUrl insteadof ModuleCustomTrait;
        TSMmodulesTrait::customModuleVersion insteadof ModuleCustomTrait;
        TSMmodulesTrait::customModuleSupportUrl insteadof ModuleCustomTrait;
        TSMmodulesTrait::title insteadof ModuleCustomTrait;
        TSMmodulesTrait::menuTitle insteadof ModuleCustomTrait;

        TSMmodulesTrait::resourcesFolder insteadof ModuleCustomTrait;
    }

    /** All constants and functions related to handling the Cart  */
    use TSMcartActions;
    /** All constants and functions related to handling the database  */
    use TSMdatabaseActions;
    /** All constants and functions related to handling the Tags  */
    use TSMtagsActions;
    /** All constants and functions related to connecting vizualizations  */
    use TSMvizActions;

    protected const ROUTE_URL = '/tree/{tree}/TSM';

    public const SHOW_RECORDS       = 'Records in tags view - Execute an action on them.';
    public const SHOW_ACTIONS       = 'Performed actions fo fill the tags.';

    // What to execute on records in the tagging service?
    // EW.H mod ... the second-level-keys are tested for actions in function postExecuteAction()
    private const EXECUTE_ACTIONS = [
        'Download records ...' => [
            'EXECUTE_DOWNLOAD_ZIP'      => '... as GEDCOM zip-file (including media files)',
            'EXECUTE_DOWNLOAD_PLAIN'    => '... as GEDCOM file (all Tags, no media files)',
            'EXECUTE_DOWNLOAD_IF'       => '... as GEDCOM file (only INDI and FAM)',
        ],
        'Visualize records in a diagram ...' => [
            'EXECUTE_VISUALIZE_TAM'     => '... using TAM',
            'EXECUTE_VISUALIZE_LINEAGE' => '... using Lineage',
            'EXECUTE_VIZ_LINEAGE_OPTNS' => '... using Lineage with Options',
        ],
    ];

    // What are the options to delete records in the tagging service?
    private const EMPTY_FORCE     = 'Deleta all records';
    private const EMPTY_ALL       = 'all records';
    private const EMPTY_SET       = 'set of records by type';
    private const EMPTY_CREATED   = 'records created by action';

    // Routes that have a record which can be added to the clipboard
    private const ROUTES_WITH_RECORDS = [
        'Family'     => FamilyPage::class,
        'Individual' => IndividualPage::class,
        'Media'      => MediaPage::class,
        'Location'   => LocationPage::class,
        'Note'       => NotePage::class,
        'Repository' => RepositoryPage::class,
        'Source'     => SourcePage::class,
        'Submitter'  => SubmitterPage::class,
        'FamilyListModule' => FamilyListModule::class,
        'IndividualListModule' => IndividualListModule::class,
    ];

    // Types of records
    // The order of the Xrefs in the Clippings Cart results from the order 
    // of the calls during insertion and in this respect is not separated 
    // according to their origin.
    // This can cause problems when passing to interfaces and functions that 
    // expect a defined sequence of tags.
    // This structure determines the order of the categories in which the 
    // records are displayed or output for further actions ( function getEmptyAction() and showTags.phtml )
    private const TYPES_OF_RECORDS = [
        'Individual' => Individual::class,
        'Family'     => Family::class,
        'Media'      => Media::class,
        'Location'   => Location::class,
        'Note'       => Note::class,
        'Repository' => Repository::class,
        'Source'     => Source::class,
        'Submitter'  => Submitter::class,
    ];

    // keep only XREFs used by Individual or Family records
    private const TODO       = 'ONLY_IF';
    // Types of records for further visualizing actions
    // This structure defines the categories which will be 
    // relevant in visualizing tools.
    private const FILTER_RECORDS = [
        'TAM' => [
                'Individual' => Individual::class,
                'Family'     => Family::class,
                ],
        'ONLY_IF' => [
                'Individual' => Individual::class,
                'Family'     => Family::class,
                ],
        'ONLY_IFS' => [
                'Individual' => Individual::class,
                'Family'     => Family::class,
                'Source'     => Source::class,
                ],
        'ONLY_IFL' => [
                'Individual' => Individual::class,
                'Family'     => Family::class,
                'Location'   => Location::class,
                ],
                ];

    /** @var int The default access level for this module.  It can be changed in the control panel. */
    protected int $access_level = Auth::PRIV_USER;

    /** @var GedcomExportService */
    private GedcomExportService $gedcom_export_service;

    /** @var LinkedRecordService */
    private LinkedRecordService $linked_record_service;

    /** @var UserService */
    private $user_service;

    /** @var Tree */
    private Tree $tree;

    // /** @var Int */
    // private int $ADD_MAX_GEN;       // EW.H mod ... get only part of tree for TAM-H-Tree

    /** @var int The number of ancestor generations to be added (0 = proband) */
    private int $levelAncestor;

    /** @var int The number of descendant generations to add (0 = proband) */
    private int $levelDescendant;

    // EW.H mod ... Output to TAM
    private const VIZdir           = Webtrees::DATA_DIR . DIRECTORY_SEPARATOR . '_toVIZ';

    /**
     * @var bool We want to have the GEDCOM-Objects exported as array
     */
    private const DO_DUMP_Ritems   = true;
    /**
     * The label ...
     * @var string
     */
    private string $huh;

    /**
     * Check for huhwt/huhwt-wttam done?
     * @var boolean
     */
    private bool $huhwttam_checked;

    /**
     * Check for huhwt/huhwt-wttam done?
     * @var boolean
     */
    private bool $huhwtlin_checked;

    /**
     * Retrieve all Record-Types
     * @var boolean
     */
    private bool $all_RecTypes;

    /**
     * where 'this' is not $this ...
     * @var TaggingServiceManager $instance
     */
    private TaggingServiceManager $instance;

    /**
     * check if this->instance is set
     * @var bool $lPdone
     */
    private bool $lPdone = false;

    /**
     * if call is coming from lists we need the origin uri
     * @var string $callingURI
     */
    private string $callingURI = '';

    /**
     * the active tag descriptor
     * @var array<int,string> $TAGoptions
     */
    private array $TAGoptions;

    /**
     * the module's name for accessing the module_settings
     * @var string $Mname
     */
    private string $Mname;

    private ModuleService $module_service;

    /** 
     * NotesModule constructor.
     *
     * @param GedcomExportService $gedcom_export_service
     * @param LinkedRecordService $linked_record_service
     */
    public function __construct(
        GedcomExportService $gedcom_export_service,
        LinkedRecordService $linked_record_service)
    {
        $this->gedcom_export_service = $gedcom_export_service;
        $this->linked_record_service = $linked_record_service;

        $this->levelAncestor       = PHP_INT_MAX;
        $this->levelDescendant     = PHP_INT_MAX;
        $this->exportFilenameDOWNL = self::FILENAME_DOWNL;
        $this->exportFilenameVIZ   = self::FILENAME_VIZ;
        $this->huh                 = json_decode('"\u210D"') . "&" . json_decode('"\u210D"') . "wt";
        $this->huhwttam_checked    = false;
        $this->huhwtlin_checked    = false;
        $this->all_RecTypes        = true;

        // EW.H mod ... read TAM-Filename from Session, otherwise: Initialize
        if (Session::has('FILENAME_VIZ')) {
            $this->exportFilenameVIZ = Session::get('FILENAME_VIZ');
        } else {
            $this->exportFilenameVIZ        = 'wt2VIZ';
            Session::put('FILENAME_VIZ', $this->exportFilenameVIZ);
        }

       // parent::__construct($gedcom_export_service, $linked_record_service);

        // EW.H mod ... we want a subdir of Webtrees::DATA_DIR for storing dumps and so on
        // - test for and create it if it not exists
        if(!is_dir(self::VIZdir)){
            //Directory does not exist, so lets create it.
            mkdir(self::VIZdir, 0755);
        }
    }

    /**
     * @param Tree $tree
     *
     * @return Menu
     */
    private function addMenuDeleteRecords (Tree $tree): Menu
    {
        return new Menu(I18N::translate('Delete records in the tagging service with selection option'),
            route('module', [
            'module' => $this->name(),
            'action' => 'Empty',
            'tree'   => $tree->name(),
            ]), 'menu-clippings-empty', ['rel' => 'nofollow']);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getTaggingServiceAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree               = Validator::attributes($request)->tree();

        $TAGoptions         = $this->TAGconfigOptions();
        $this->TAGoptions   = $TAGoptions;
        $_optInd            = (int) $this->getPreference('TAG_Option', '0');
        $this->activeTAG    = $TAGoptions[$_optInd];

        $xrefsN_ar          = [];
        $tagTxt_ar          = [];
        $_activeTAG         = $this->activeTAG . ':';

        if ( isset($_SERVER['HTTPS_HOST']))
            $_currUrl       = $_SERVER['HTTPS_HOST'];
        else
            $_currUrl       = $_SERVER['HTTP_HOST'];
        $_currUrl .= $_SERVER['REQUEST_URI'];

        Session::put('TSMactiveTAG', $_activeTAG );             // we need it later to check validity
        Session::put('TSMlocation', $_currUrl);                 // we need it later to check validity

        $this->tagsNone     = I18N::translate('(none)');
        $this->tagsAction   = $this->tagsNone;                    // this is the default for untagged xrefs

        $xrefsC_ar          = $this->getXrefsInCart($tree);                 // get the cart
        if (count($xrefsC_ar) > 0)                                              // if there are any xrefs ...
            $this->put_TagsActs($tree, $this->tagsAction, '_');                 // ... store the default too

        [ $xrefsN_ar, $tagTxt_ar ] = $this->getTagNotes_All($tree);
        foreach( $xrefsN_ar as $_xref => $_tagTxt) {
            if (str_starts_with($_tagTxt, $_activeTAG)) {
               $this->put_TagsActs($tree, $_tagTxt, $_xref);
            }
        }

        $_xrefs             = array_keys($xrefsN_ar);                               // we need the pure xrefs of relevant notes
        $xrefsN             = array_map('strval', $_xrefs);

        $_xrefs             = array_keys($xrefsC_ar);                               // we need the pure xrefs of actual cart
        $xrefsC             = array_map('strval', $_xrefs);

        foreach ($xrefsC_ar as $xref => $actions) {
            $_xref      = (string)$xref;
            $this->put_Tags($tree, $_xref);
        }

        $links              = $this->get_linkedXREFs($tree, $xrefsN)->toArray();
        $tags               = $this->get_Tags();
        $_tree              = $tree->name();
        $none_action        = $this->tagsNone;

        foreach (array_values($links) as $linkXN) {
            $_xrefC         = $linkXN->l_from;
            $_xrefN         = $linkXN->l_to;
            $_tAction       = $xrefsN_ar[$_xrefN];
            if (($tags[$_tree][$_xrefC] ?? '_NIX_') === '_NIX_') {
                $tags[$_tree][$_xrefC] = $_tAction;
                Session::put('tags', $tags);
            } else {
                $tagsActs   = $tags[$_tree][$_xrefC];
                if (!str_contains($tagsActs, $_tAction)) {
                    if ($tagsActs == $none_action) {
                        $tagsActs = $_tAction;
                    } else {
                        $tagsActs = $tagsActs . ';' . $_tAction;
                    }
                    $tags[$_tree][$_xrefC] = $tagsActs;
                    Session::put('tags', $tags);
                }
            }
        }

        return $this->getShowTagsAction($request);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getShowTagsAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);

        $recordTypes        = $this->collectRecordsInTags($tree, self::TYPES_OF_RECORDS);

        $recordFilter = self::FILTER_RECORDS[self::TODO];
        $recordTexecs = array_intersect_key($recordTypes, $recordFilter);
        $recordTypes = $recordTexecs;

        $this->tagsXREFs    = $this->getXREFstruct($tree);

        $tagsLabels         = $this->get_TagsActsMap($tree);
        $tagsValues         = $this->get_TagsActs($tree);
        $title              = I18N::translate('Tagging service manager');

        $routeSDbAjax = e(route(TaggingServiceManagerModule::class, ['module' => $this->name(), 'tree' => $tree->name()]));

        $routeWReAjax = e(route(TaggingServiceManagerModule::class, ['module' => $this->name(), 'tree' => $tree->name(), 'action' => 'redoWindow']));

        $routeATcAjax = e(route(TSMchooseATagModal::class, ['module' => $this->name(), 'tree' => $tree->name(), 'action' => 'chooseActiveTag', 'activeTag' => $this->activeTAG ]));

        return $this->viewResponse($this->name() . '::' . 'showTags/showTags', [
            'module'        => $this->name(),
            'types'         => self::TYPES_OF_RECORDS,
            'recordTypes'   => $recordTypes,
            'title'         => $title,
            'header_recs'   => I18N::translate(self::SHOW_RECORDS),
            'header_acts'   => I18N::translate(self::SHOW_ACTIONS),
            'activeTag'     => $this->activeTAG,
            'TAGoptions'    => $this->TAGoptions,
            'tagsLabels'    => $tagsLabels,
            'tagsValues'    => $tagsValues,
            'routeSDbAjax'  => $routeSDbAjax,
            'routeATcAjax'  => $routeATcAjax,
            'routeWReAjax'  => $routeWReAjax,
            'tagsXREFs'     => $this->tagsXREFs,
            'tree'          => $tree,
            'stylesheet'    => $this->assetUrl('css/tsm.css'),
            'javascript'    => $this->assetUrl('js/tsm.js'),
        ]);
    }

    /**
     * Put class-name into options-text for later on increment/decrement value in view
     * 
     * @param string        $text
     * @param string        $subst1
     * @param string        $subst2
     * @param string        $tosubst
     * @param string        $cname
     * @param int           $value
     * 
     * @return string
     */
    private function substText($text, $subst1, $subst2, $tosubst, $cname, $value)
    {
        $txt_t = I18N::translate($text, $subst1, $subst2);
        $txt_c = '<span class="' . $cname . '">' . $value . '</span>';
        $txt_r = str_replace($tosubst, $txt_c, $txt_t);
        return $txt_r;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getExecuteAction(ServerRequestInterface $request): ResponseInterface
    {
        //dependency check
        if (!$this->huhwttam_checked) {
            $ok = class_exists("HuHwt\WebtreesMods\TAMchart\TAMaction", true);
            if (!$ok) {
                $wttam_link = '(https://github.com/huhwt/huhwt-wttam)';
                $wttam_missing = I18N::translate('Missing dependency - Install %s!', 'TAM'); // EW.H - Mod ... make warning
                $theMessage = $wttam_missing . ' -> ' . $wttam_link;
                FlashMessages::addMessage($theMessage);
            }
            $this->huhwttam_checked = true;
        }

        if (!$this->huhwtlin_checked) {
            $ok = class_exists("HuHwt\WebtreesMods\LINchart\LINaction", true);
            if (!$ok) {
                $wtlin_link = '(https://github.com/huhwt/huhwt-wtlin)';
                $wtlin_missing = I18N::translate('Missing dependency - Install %s!', 'LIN'); // EW.H - Mod ... make warning
                $theMessage = $wtlin_missing . ' -> ' . $wtlin_link;
                FlashMessages::addMessage($theMessage);
            }
            $this->huhwtlin_checked = true;
        }

        $tree = Validator::attributes($request)->tree();
        $user = Validator::attributes($request)->user();

        $first = ' -> Webtrees Standard action';
        $options_arr = array();
        foreach (self::EXECUTE_ACTIONS as $opt => $actions) {
            $actions_arr = array();
            foreach ($actions as $action => $text) {
                $atxt = I18N::translate($text);
                $atxt = $atxt . $first;
                $first = '';
                $actions_arr[$action] = $atxt;
            }
            $options_arr[$opt] = $actions_arr;
        }

        $title = I18N::translate('Execute an action on records in the tagging service');
        $label = I18N::translate('Privatize options');

        return $this->viewResponse($this->name() . '::' . 'execute', [
            'options'    => $options_arr,
            'title'      => $title,
            'label'      => $label,
            'is_manager' => Auth::isManager($tree, $user),
            'is_member'  => Auth::isMember($tree, $user),
            'module'     => $this->name(),
            'tree'       => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postExecuteAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $option = Validator::parsedBody($request)->string('option', 'none');

        switch ($option) {
        // We want to download gedcom as zip ...
            // ... we use the default download-action
            case 'EXECUTE_DOWNLOAD_ZIP':
                $url = route('module', [
                    'module' => parent::name(),
                    'action' => 'DownloadForm',
                    'tree'   => $tree->name(),
                ]);
                $redobj = redirect($url);
                return redirect($url);

        // From hereon we are dealing with plain textual gedcom 

            // all kinds of records in TSM - download as file
            case 'EXECUTE_DOWNLOAD_PLAIN':
                return $this->tmsDownloadAction($request, 'PLAIN', 'DOWNLOAD');

            // only INDI and FAM records from TSM - download as file
            case 'EXECUTE_DOWNLOAD_IF':
                return $this->tmsDownloadAction($request, 'ONLY_IF', 'DOWNLOAD');

            // only INDI and FAM records - postprocessing in TAM
            case 'EXECUTE_VISUALIZE_TAM':
                return $this->tmsDownloadAction($request, 'ONLY_IF', 'VIZ=TAM');

            // only INDI and FAM records - postprocessing in LINEAGE
            case 'EXECUTE_VISUALIZE_LINEAGE':
                return $this->tmsDownloadAction($request, 'ONLY_IF', 'VIZ=LINEAGE');
            
            // only INDI and FAM records - postprocessing in LINEAGE
            case 'EXECUTE_VIZ_LINEAGE_OPTNS':
                return $this->tmsDownloadAction($request, 'ONLY_IFS', 'VIZ=LINEAGE');
            
            default;
                break;

        }
        $url = route('module', [
            'module' => $this->name(),
            'action' => 'ShowTags',
            'tree'   => $tree->name(),
        ]);
        return redirect($url);
    }

    /**
     * postprocessing GEDCOM:
     * - download as plain file 
     *   - complete as is from Notes
     *   - reduced, only INDI and FAM
     * - preparation for and call of VIZ=TAM
     * - preparation for and call of VIZ=LINEAGE
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     *
    //  * @throws \League\Flysystem\FileExistsException
    //  * @throws \League\Flysystem\FileNotFoundException
     */
    public function tmsDownloadAction(ServerRequestInterface $request, string $todo, string $action): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $privatizeExport = Validator::parsedBody($request)->string('privatize_export', 'none');
        $accessLevel = $this->getAccessLevel($privatizeExport, $tree);
        $encoding = 'UTF-8';
        $line_endings = 'LF';

        // there may be the situation that tags is emptied in meanwhile
        // due to session timeout and setting up a new session or because of
        // browser go-back after a empty-all action
        if ($this->isTagsEmpty($tree)) {
            $url = route('module', [
                'module' => $this->name(),
                'action' => 'ShowTags',
                'tree'   => $tree->name(),
            ]);
            return redirect($url);
        }

        $recordTypes = $this->collectRecordKeysInCart($tree, self::TYPES_OF_RECORDS);
        // keep only XREFs used by Individual or Family records
        if ( str_starts_with($todo,'ONLY_IF') ) {
            $recordFilter = self::FILTER_RECORDS[$todo];
            $recordTexecs = array_intersect_key($recordTypes, $recordFilter);
            $recordTypes = $recordTexecs;
        }
        // prepare list of remaining xrefs - unordered but separated by types
        $xrefs = [];
        foreach ($recordTypes as $key => $Txrefs) {
            foreach ($Txrefs as $xref) {
                $xrefs[] = $xref;
            }
        }

        $records = $this->getRecordsForExport($tree, $xrefs, $accessLevel);

        /**
         *  We want to download the plain gedcom ...
         */

        if ( $action == 'DOWNLOAD' ) {
            // $http_stream = $stream_factory->createStreamFromResource($tmp_stream);

            $download_filename = $this->exportFilenameDOWNL;
            if ( str_starts_with($todo,'ONLY_IF') ) {
                $download_filename .= '_IF_';
            }
            $download_filename .= '(' . $xrefs[0] . ')';

            return $this->gedcom_export_service->downloadResponse($tree, false, $encoding, 'none', $line_endings, $download_filename, 'gedcom', $records);

        }

        /**
         *  We want to postprocess the gedcom in a Vizualising-Tool ...
         * 
         *  ... the record objects must be transformed to json
         */

        // Transform record-Objects to simple php-Array-Items
        $o_items = [];
        foreach ($records as $ritem) {
            $o_items[] = $ritem;
        }
        $r_items = (array)$o_items;

        if ( self::DO_DUMP_Ritems ) {
            // We want the php-Array-Items dumped
            $arr_items = array();
            $ie = count($r_items);
            for ( $i = 0; $i < $ie; $i++) {
                $xrefi = $xrefs[$i];
                $arr_items[$xrefi] = $r_items[$i];
            }
            $this->dumpArray($arr_items, $action . 'records');
        }

        $r_string = implode("\n", $r_items);
        $arr_string = array();
        $arr_string["gedcom"] = $r_string;

        // We want to have the gedcom as external file too
        $this->dumpArray($arr_string,  $action . 'gedcom');

        // Encode the array into a JSON string.
        $encodedString = json_encode($arr_string);

        switch ($action) {

            case 'VIZ=TAM':
                $ok = class_exists("HuHwt\WebtreesMods\TAMchart\TAMaction", true);
                if ( $ok ) {
                    // Save the JSON string to SessionStorage.
                    Session::put('wt2TAMgedcom', $encodedString);
                    Session::put('wt2TAMaction', 'wt2TAMgedcom');
                    // Switch over to TAMaction-Module
                    // TODO : 'module' is hardcoded - how to get the name from foreign PHP-class 'TAMaction'?
                    $url = route('module', [
                        'module' => '_huhwt-wttam_',
                        'action' => 'TAM',
                        'actKey' => 'wt2TAMaction',
                        'tree'   => $tree->name(),
                    ]);
                    return redirect($url);
                }
                break;

            case 'VIZ=LINEAGE':
                $ok = class_exists("HuHwt\WebtreesMods\LINchart\LINaction", true);
                if ( $ok ) {
                    Session::put('wt2LINgedcom', $encodedString);
                    Session::put('wt2LINaction', 'wt2LINgedcom');
                    Session::put('wt2LINxrefsI', $recordTypes['Individual']);
                    // Switch over to TAMaction-Module
                    // TODO : 'module' is hardcoded - how to get the name from foreign PHP-class 'LINaction'?
                    $url = route('module', [
                        'module' => '_huhwt-wtlin_',
                        'action' => 'LIN',
                        'actKey' => 'wt2LINaction',
                        'tree'   => $tree->name(),
                    ]);
                    return redirect($url);
                }
                break;

        }
        // We try to execute something that is not known by now ...
        FlashMessages::addMessage(I18N::translate("You have tried '%s' - it is not implemented yet.", e($action)));
        return redirect((string) $request->getUri());
    }

    /**
     * delete all records in the tagging service or delete a set grouped by type of records
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getEmptyAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $title = I18N::translate('Delete all records, a set of records of the same type, or selected records');
        $label = I18N::translate('Delete');
        $labelType = I18N::translate('Delete all records of a type');
        $recordTypes = $this->countRecordTypesInCart($tree, self::TYPES_OF_RECORDS);

        $plural = I18N::translate(self::EMPTY_ALL) . ' ' . $badge = view('components/badge', ['count' => $recordTypes['all']]);
        $options[self::EMPTY_ALL] = I18N::plural('this record', $plural, $recordTypes['all']);
        unset($recordTypes['all']);

        $selectedTypes = [];
        if (count($recordTypes) > 1) {
            // $recordTypesList = implode(', ', array_keys($recordTypes));
            $options[self::EMPTY_SET] = I18N::translate(self::EMPTY_SET) . ':'; // . $recordTypesList;
            $options[self::EMPTY_CREATED] = I18N::translate(self::EMPTY_CREATED);
            $i = 0;
            foreach ($recordTypes as $type => $count) {
                $selectedTypes[$i] = 0;
                $i++;
            }
        } else {
            $headingTypes = '';
        }

        $selectedActions = $this->get_TagsActsMap($tree);

        return $this->viewResponse($this->name() . '::' . 'empty', [
            'module'         => $this->name(),
            'options'        => $options,
            'title'          => $title,
            'label'          => $label,
            'labelType'      => $labelType,
            'recordTypes'    => $recordTypes,
            'selectedTypes'  => $selectedTypes,
            'selectedActions'=> $selectedActions,
            'tree'           => $tree,
        ]);
    }

    /**
     * delete all records in the tagging service or delete a set grouped by type of records
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getEmptyForceAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        assert($tree instanceof Tree);

        $params = $_GET['params'];
        $retRoute = $params['called_by'];

        $this->clean_Tags($tree);
        $this->clean_TagsActs($tree);

        return redirect($retRoute);
    }
    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postEmptyAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $option = Validator::parsedBody($request)->string('option');

        switch ($option) {
            case self::EMPTY_ALL:
                $this->clean_Tags($tree);
                $this->clean_TagsActs($tree);
                $url = route('module', [
                    'module'      => $this->name(),
                    'action'      => 'ShowTags',
                    'description' => $this->description(),
                    'tree'        => $tree->name(),
                ]);
                break;

            case self::EMPTY_SET:
                $this->doEmpty_SetAction($tree, $request);
                $url = route('module', [
                    'module'      => $this->name(),
                    'action'      => 'ShowTags',
                    'description' => $this->description(),
                    'tree'        => $tree->name(),
                ]);
                break;

            case self::EMPTY_CREATED:
                $this->doEmpty_CreatedAction($tree, $request);
                $url = route('module', [
                    'module'      => $this->name(),
                    'action'      => 'ShowTags',
                    'description' => $this->description(),
                    'tree'        => $tree->name(),
                ]);
                break;
    
            default;
                $txt_option = I18N::translate($option);
                FlashMessages::addMessage(I18N::translate("You have tried '%s' - it is not implemented yet.", e($txt_option)));
                return redirect((string) $request->getUri());
        }

        return redirect($url);
    }

    /**
     * delete selected types of record from the tagging service
     *
     * @param Tree  $tree
     * @param ServerRequestInterface $request
     *
     */
    public function doEmpty_SetAction(Tree $tree, ServerRequestInterface $request): void
    {
        $recordTypes = $this->collectRecordKeysInCart($tree, self::TYPES_OF_RECORDS);
        foreach ($recordTypes as $key => $class) {              // test if record types ...
            $delKey = Validator::parsedBody($request)->string($key, 'none');  // ... are listed in request
            if ($delKey !== 'none') {
                unset($recordTypes[$key]);                      // remove xrefs-chain from actual known xrefs
            }
        }
        $newCart = [];
        foreach ($recordTypes as $key => $xrefs) {              // prepare list of remaining xrefs
            foreach ($xrefs as $xref) {
                $newCart[] = $xref;
            }
        }
        $tags = Session::get('tags', []);
        $xrefs = array_keys($tags[$tree->name()] ?? []);
        $xrefs = array_map('strval', $xrefs);           // PHP converts numeric keys to integers.
        $_tree = $tree->name();
        foreach ($xrefs as $xref) {
            if (!in_array($xref, $newCart)) {                   // test if xref is already wanted
                unset($tags[$_tree][$xref]);
            }
        }
        Session::put('tags', $tags);
    }

    /**
     * delete selected types of record from the tagging service
     *
     * @param ServerRequestInterface $request
     *
     */
    private function doEmpty_CreatedAction(Tree $tree, ServerRequestInterface $request): string
    {
        $_tree = $tree->name();

        // the actual tagsActs
        $tagsAct_s = Session::get('tagsActs', []);
        if (empty($tagsAct_s)) 
            return (string) $this->count_TagsRecords($tree);
        $tagsAct_T = $tagsAct_s[$_tree] ?? [];
        if (empty($tagsAct_T)) 
            return (string) $this->count_TagsRecords($tree);

        $tags = Session::get('tags', []);
        $tagsT = $tags[$_tree] ?? [];
        if (!empty($tagsT)) {
            foreach ($tagsAct_T as $tagsAct => $val) {                                        // test if any tagsAct ...
                $delKey = Validator::parsedBody($request)->string($tagsAct, 'none');  // ... is listed in request
                if ($delKey !== 'none') {
                    $cAct = str_contains($tagsAct,'|') ? substr($tagsAct,0,stripos($tagsAct,'|')) : $tagsAct;
                    foreach ($tagsT as $xref => $xref_action) {
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
                    Session::put('tags', $tags);
                    $this->clean_TagsActs_cact($tree, $tagsAct);
                }
            }
        }
        return (string) $this->count_TagsRecords($tree);
    }

    /**
     * delete one record from the tagging service
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postRemoveAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $xref = Validator::queryParams($request)->isXref()->string('xref');

        $tags = Session::get('tags', []);
        unset($tags[$tree->name()][$xref]);
        Session::put('tags', $tags);

        $this->remove_Cart($tree, $xref);

        $url = route('module', [
            'module'      => $this->name(),
            'action'      => 'ShowTags',
            'description' => $this->description(),
            'tree'        => $tree->name(),
        ]);

        return redirect($url);
    }

    /**
     * get access level based on selected option and user level
     *
     * @param string $privatizeExport
     * @param Tree $tree
     * @return int
     */
    private function getAccessLevel(string $privatizeExport, Tree $tree): int
    {

        if ($privatizeExport === 'none' && !Auth::isManager($tree)) {
            $privatizeExport = 'member';
        } elseif ($privatizeExport === 'gedadmin' && !Auth::isManager($tree)) {
            $privatizeExport = 'member';
        } elseif ($privatizeExport === 'user' && !Auth::isMember($tree)) {
            $privatizeExport = 'visitor';
        }

        switch ($privatizeExport) {
            case 'gedadmin':
                return Auth::PRIV_NONE;
            case 'user':
                return Auth::PRIV_USER;
            case 'visitor':
                return Auth::PRIV_PRIVATE;
            case 'none':
            default:
                return Auth::PRIV_HIDE;
        }
    }

    /**
     * Count the records of each type in the tagging service.
     *
     * @param Tree $tree
     * @param array $recordTypes
     *
     * @return int[]
     */
    private function countRecordTypesInCart(Tree $tree, array $recordTypes): array
    {
        $records = $this->getRecordsInCart($tree);
        $recordTypesCount = [];                  // type => count
        $recordTypesCount['all'] = count($records);
        foreach ($recordTypes as $key => $class) {
            foreach ($records as $record) {
                if ($record instanceof $class) {
                    if (array_key_exists($key, $recordTypesCount)) {
                        $recordTypesCount[$key]++;
                    } else {
                        $recordTypesCount[$key] = 1;
                    }
                }
            }
        }
        return $recordTypesCount;
    }

    /**
     * Collect the keys of the records of each type in the tagging service.
     * The order of the Xrefs in the tags results from the order of
     * the calls during insertion and is not further separated according to
     * their origin.
     * This function distributes the Xrefs according to their origin to a predefined structure.
     *
     * @param Tree $tree
     * @param array $recordTypes
     *
     * @return array    // string[] string[]
     */
    private function collectRecordKeysInCart(Tree $tree, array $recordTypes): array
    {
        $records = $this->getRecordsInCart($tree);
        $recordKeyTypes = array();                  // type => keys
        foreach ($recordTypes as $key => $class) {
            $recordKeyTypeXrefs = [];
            foreach ($records as $record) {
                if ($record instanceof $class) {
                    $xref = $this->getXref_fromRecord($record);
                    $recordKeyTypeXrefs[] = $xref;
                }
            }
            if ( count($recordKeyTypeXrefs) > 0) {
                $recordKeyTypes[strval($key) ] = $recordKeyTypeXrefs;
            }
        }
        return $recordKeyTypes;
    }

    /**
     * Collect the records of each type in the tagging service.
     * The order of the Xrefs in the tags results from the sequence of the calls
     * during insertion and may be relevant for subsequent actions.
     * On the other hand, the records must also be separated according to their
     * origin and put in a defined order in this respect.
     * For this reason, the records are not output directly, but are inserted
     * into a structure that is predefined and specifies the sequence.
     *
     * @param Tree $tree
     * @param array $recordTypes
     *
     * @return array    // string[] GedcomRecord []
     */
    private function collectRecordsInTags(Tree $tree, array $recordTypes): array
    {
        $records = $this->getRecordsInCart($tree);
        $recordKeyTypes = array();                  // type => keys
        foreach ($recordTypes as $key => $class) {
            $recordKeyTypeXrefs = [];
            foreach ($records as $record) {
                if ($record instanceof $class) {
                    $recordKeyTypeXrefs[] = $record;
                }
            }
            if ( count($recordKeyTypeXrefs) > 0) {
                $recordKeyTypes[strval($key) ] = $recordKeyTypeXrefs;
            }
        }
        return $recordKeyTypes;
    }

    /**
     * Get the records in the tagging service. 
     * There may be use cases where it makes sense to output the records sorted
     * by their Xrefs, but for our purposes it is rather disadvantageous,
     * so sorting is optional and disabled by default.
     *
     * @param Tree $tree
     * @param bool $do_sort
     *
     * @return array
     */
    private function getRecordsInCart(Tree $tree, bool $do_sort=false): array
    {
        $xrefs = $this->get_CartXrefs($tree);
        $records = array_map(static function (string $xref) use ($tree): ?GedcomRecord {
            return Registry::gedcomRecordFactory()->make($xref, $tree);
        }, $xrefs);

        // some records may have been deleted after they were added to the tags, remove them
        // $records = array_filter($records);

        if ($do_sort) {
            // group and sort the records
            uasort($records, static function (GedcomRecord $x, GedcomRecord $y): int {
                return $x->tag() <=> $y->tag() ?: GedcomRecord::nameComparator()($x, $y);
            });
        }

        return $records;
    }

    /**
     * Get the XREF for the record in the tagging service.
     *
     * @param GedcomRecord $record
     *
     * @return string 
     */
    private function getXref_fromRecord(GedcomRecord $record): string
    {
        $xref = $record->xref();
        return $xref;
    }

    /**
     * get GEDCOM records from array with XREFs ready to write them to a file
     * and export media files to zip file
     *
     * @param Tree $tree
     * @param array $xrefs
     * @param int $access_level
     *
     * @return Collection
     */
    private function getRecordsForExport(Tree $tree, array $xrefs, int $access_level): Collection
    {
        $records = new Collection();
        foreach ($xrefs as $xref) {
            $object = Registry::gedcomRecordFactory()->make($xref, $tree);
            // The object may have been deleted since we added it to the tags ...
            if ($object instanceof GedcomRecord) {
                $record = $object->privatizeGedcom($access_level);
                $record = $this->removeLinksToUnusedObjects($record, $xrefs);
                $records->add($record);
            }
        }
        return $records;
    }

    /**
     * remove links to objects that aren't in the tags
     *
     * @param string $record
     * @param array $xrefs
     *
     * @return string
     */
    private function removeLinksToUnusedObjects(string $record, array $xrefs): string
    {
        preg_match_all('/\n1 ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@(\n[2-9].*)*/', $record, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!in_array($match[1], $xrefs, true)) {
                $record = str_replace($match[0], '', $record);
            }
        }
        preg_match_all('/\n2 ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@(\n[3-9].*)*/', $record, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!in_array($match[1], $xrefs, true)) {
                $record = str_replace($match[0], '', $record);
            }
        }
        preg_match_all('/\n3 ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@(\n[4-9].*)*/', $record, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!in_array($match[1], $xrefs, true)) {
                $record = str_replace($match[0], '', $record);
            }
        }
        return $record;
    }

    /**
     * Recursive function to traverse the tree and count the maximum ancestor generation
     *
     * @param Individual $individual
     *
     * @return int
     */
    protected function countAncestorGenerations(Individual $individual): int
    {
        $leave = true;
        $countMax = -1;
        foreach ($individual->childFamilies() as $family) {
            foreach ($family->spouses() as $parent) {
                // there are some parent nodes/trees; get the maximum height of parent trees
                $leave = false;
                $countSubtree = $this->countAncestorGenerations($parent);
                if ($countSubtree > $countMax) {
                    $countMax = $countSubtree;
                }
            }
        }
        If ($leave) {
            return 1;               // leave is reached
        } else {
            return $countMax + 1;
        }
    }

    /**
     * Recursive function to traverse the tree and count the maximum descendant generation
     *
     * @param Individual $individual
     *
     * @return int
     */
    protected function countDescendantGenerations(Individual $individual): int
    {
        $leave = true;
        $countMax = -1;
        foreach ($individual->spouseFamilies() as $family) {
            foreach ($family->children() as $child) {
                // there are some child nodes/trees; get the maximum height of child trees
                $leave = false;
                $countSubtree = $this->countDescendantGenerations($child);
                if ($countSubtree > $countMax) {
                    $countMax = $countSubtree;
                }
            }
        }
        If ($leave) {
            return 1;               // leave is reached
        } else {
            return $countMax + 1;
        }
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        /* I18N: Description of the module */
        return I18N::translate(self::CUSTOM_DESCRIPTION);
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

    /**
     * Additional/updated translations.
     *
     * @param string $language
     *
     * @return array<string,string>
     */
    public function customTranslations(string $language): array
    {
        // no differentiation according to language variants
        $_language = substr($language, 0, 2);
        $ret = [];
        $languageFile = $this->resourcesFolder() . 'lang' . DIRECTORY_SEPARATOR . $_language . '.po';
        if (file_exists($languageFile)) {
            $ret = (new Translation($languageFile))->asArray();
        }
        return $ret;
    }

    /**
     *  bootstrap
     */
    public function boot(): void
    {
        $router_container = Registry::container()->get(RouterContainer::class);
        assert($router_container instanceof RouterContainer);
        // $router = $router_container->getMap();

        $router = Registry::routeFactory()->routeMap();

        $router->attach('', '/tree/{tree}', static function (Map $router) {

            $router->get(TaggingServiceManagerModule::class, '/TSM')
                ->allows(RequestMethodInterface::METHOD_POST);
            $router->get(TSMCreateNoteModal::class, '/TSM/create-tag-object');
            $router->post(TSMCreateNoteAction::class, '/TSM/create-tag-object');
            $router->get(TSMchooseATagModal::class, '/TSM/choose-active-tagM');
            $router->post(TSMchooseATagAction::class, '/TSM/choose-active-tagA');
        });

        // Here is also a good place to register any views (templates) used by the module.
        // This command allows the module to use: view($this->name() . '::', 'fish')
        // to access the file ./resources/views/fish.phtml
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

        View::registerCustomView('::components/TSMbadgedText', $this->name() . '::components/TSMbadgedText');
        View::registerCustomView('::components/TSMbadge', $this->name() . '::components/TSMbadge');

        View::registerCustomView('::modals/create-note-objectTSM', $this->name() . '::modals/TSMcreate-note-object');
        View::registerCustomView('::modals/note-object-fieldsTSM', $this->name() . '::modals/TSMnote-object-fields');

        View::registerCustomView('::modals/chooseActiveTag', $this->name(). '::modals/TSMchoose-ActiveTag');
        View::registerCustomView('::modals/changedActiveTag', $this->name(). '::modals/TSMchanged-ActiveTag');

        View::registerCustomView('::modals/footer-checked', $this->name(). '::modals/TSMfooter-checked');
        View::registerCustomView('::icons/checked', $this->name(). '::icons/check');
        View::registerCustomView('::icons/eye', $this->name(). '::icons/eye');
        View::registerCustomView('::icons/eye-slash', $this->name(). '::icons/eye-slash');
        View::registerCustomView('::icons/redo', $this->name(). '::icons/redo');

        $TSMjs = $this->resourcesFolder() . 'js/TSMtable-actions.js';
        Session::put('TSMtable-actions.js', $TSMjs);
        $TSMcss = $this->resourcesFolder() . 'css/TSMtable-actions.css';
        Session::put('TSMtable-actions.css', $TSMcss);

        Session::put('TSMclassName', $this->name());               // we need it later

    }

    /**
     * dump array as json to text-file
     * 
     * @param array $theArray
     */
    public function dumpArray(array &$theArray, string $fileName)
    {
        //Encode the array into a JSON string.
        $encodedString = json_encode($theArray);

        //Save the JSON string to a text file.
        $_fName = SELF::VIZdir . DIRECTORY_SEPARATOR . $fileName;
        file_put_contents($_fName, $encodedString, LOCK_EX);

    }

    /**
     * store array as json to session
     * 
     * @param array $theArray
     */
    public function saveArrayToSession(array &$theArray, string $storeName)
    {
        //Encode the array into a JSON string.
        $encodedString = json_encode($theArray);

        //Save the JSON string to SessionStorage.
        Session::put($storeName, $encodedString);

    }

}
