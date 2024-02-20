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

namespace HuHwt\WebtreesMods\TaggingServiceManager\Http\RequestHandlers;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Services\PendingChangesService;

use InvalidArgumentException;

use Illuminate\Database\Capsule\Manager as DB;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use HuHwt\WebtreesMods\TaggingServiceManager\Traits\TSMconfigTrait;
use HuHwt\WebtreesMods\TaggingServiceManager\Traits\TSMdatabaseActions;
use HuHwt\WebtreesMods\TaggingServiceManager\Traits\TSMtagsActions;

/**
 * Process a form to create a new note object.
 */
class TSMchooseATagAction implements RequestHandlerInterface
{
    /** All constants and functions related to handling the Tags  */
    use TSMtagsActions;

    use TSMconfigTrait;

    /** All constants and functions related to handling the database  */
    use TSMdatabaseActions;

    private $Mname;

    /**
     * A unique internal name for this module (based on the installation folder).
     *
     * @return string
     */
    private function getMname(): string
    {
        return $this->Mname;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $_TSMclassName  = Session::get('TSMclassName');
        $this->Mname    = $_TSMclassName;

        $_activeTAG     = trim(Session::get('TSMactiveTAG'), ' :\n\r\t\v\x00');
        $_redirURI      = Session::get('TSMlocation');


        $tree           = Validator::attributes($request)->tree();
        $newATag        = Validator::parsedBody($request)->isNotEmpty()->string('ChooseTag');

        $tagOptions     = $this->TAGconfigOptions();

        $newATind       = -1;
        foreach ($tagOptions as $key => $value) {
            if ( $value == $newATag ) {
                $newATind = $key;
            }
        }
        if ($newATind >= 0) {
            $this->setPreferenceNamed($this->getMname(), 'TAG_Option', (string) $newATind);
            // $this->clean_Tags($tree);
            $this->clean_TagsActs($tree);
        }
        // value and text are for autocomplete
        // html is for interactive modals

        $_html = view('modals/changedActiveTag', [
            'title' => I18N::translate("'Active Tag' is changed") . '   ...   ' . $_activeTAG . ' -> ' . $newATag,
            'name'  => $newATag,
            'url'   => $_redirURI,
        ]);

        $_response = response([
            'value' => $newATag,
            'text'  => '_',
            'html'  => $_html,
        ]);
        return $_response;
    }

}
