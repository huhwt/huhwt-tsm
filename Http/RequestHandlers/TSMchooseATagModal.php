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

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;

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
class TSMchooseATagModal implements RequestHandlerInterface
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
     * Get a module setting. Return a default if the setting is not set.
     *
     * @param string $setting_name
     * @param string $default
     *
     * @return string
     */
    private function getPreferenceX(string $setting_name, string $default = ''): string
    {
        $_TSMclassName = Session::get('TSMclassName');

        return DB::table('module_setting')
            ->where('module_name', '=', $_TSMclassName)
            ->where('setting_name', '=', $setting_name)
            ->value('setting_value') ?? $default;
    }

    /**
     * Domain Tag Actions
     *
     * @return array<int,string>
     */
    private function TAGconfigOptionsX(): array
    {
        $TAGoption_stock    = $this->getPreferenceNamed($this->getMname(), 'TAG_option_stock','TAG');

        $tCOs               = explode(';', $TAGoption_stock);

        $tagCOtest = [];
        foreach( $tCOs as $tCO ) {
            if ( $tCO > '') {
                $tagCOtest[] = $tCO;
            }
        }

        return $tagCOtest;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {

        $_TSMclassName      = Session::get('TSMclassName');
        $this->Mname        = $_TSMclassName;

        $tree        = Validator::attributes($request)->tree();

        $action      = Validator::queryParams($request)->string('action');

        $activeTAG   = Validator::queryParams($request)->string('activeTag');

        $CRoute      = e(route(TSMchooseATagAction::class, ['tree' => $tree->name()]));

        if ( $action == 'chooseActiveTag' ) {
            $TAGoptions  = $this->TAGconfigOptions();

            return response(view('modals/chooseActiveTag', [
                'tree'       => $tree,
                'activeTag'  => $activeTAG,
                'TagOptions' => $TAGoptions,
                'Croute'     => $CRoute,
            ]));
        }

        return response($activeTAG);
    }
}
