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

use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function response;
use function view;

/**
 * Show a form to create a new note object.
 */
class TSMCreateNoteModal implements RequestHandlerInterface
{
    /**
     * Show a form to create a new note object.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree       = Validator::attributes($request)->tree();

        $activeTag  = Validator::queryParams($request)->string('activeTag', '');

        return response(view('modals/create-note-objectTSM', [
            'tree'      => $tree,
            'activeTag' => $activeTag,
        ]));
    }
}
