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

use HuHwt\WebtreesMods\TaggingServiceManager\Traits\TSMdatabaseActions;

/**
 * Process a form to create a new note object.
 */
class TSMCreateNoteAction implements RequestHandlerInterface
{
    /** All constants and functions related to handling the database  */
    use TSMdatabaseActions;

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $_activeTAG  = Session::get('TSMactiveTAG');
        $tree        = Validator::attributes($request)->tree();
        $note_txt    = trim(Validator::parsedBody($request)->isNotEmpty()->string('note'));
        $restriction = Validator::parsedBody($request)->string('restriction');

        $_problem    = $this->testTAG($tree, $note_txt, $_activeTAG);                       // we do some complex testing if declaration is OK ...
        if ($_problem)                                                                      // ... there is some problematic ...
            return $_problem;                                                                   // ... break

        $note        = Registry::elementFactory()->make('NOTE:CONT')->canonical($note_txt);
        $restriction = Registry::elementFactory()->make('NOTE:RESN')->canonical($restriction);

        $gedcom = '0 @@ NOTE ' . strtr($note, ["\n" => "\n1 CONT "]);

        if ($restriction !== '') {
            $gedcom .= "\n1 RESN " . strtr($restriction, ["\n" => "\n2 CONT "]);
        }

        $record = $this->createNote($tree, $gedcom);

        // value and text are for autocomplete
        // html is for interactive modals
        return response([
            'value' => '@' . $record->xref() . '@',
            'text'  => view('selects/note', ['note' => $record]),
            'html'  => view('modals/record-created', [
                'title' => I18N::translate('The tag has been created'),
                'name'  => $record->fullName() . '->' . $gedcom,
                'url'   => $record->url(),
            ]),
        ]);
    }

    /**
     * Create a new note from GEDCOM data.
     *
     * @param string $gedcom
     *
     * @return Note
     * @throws InvalidArgumentException
     */
    public function createNote(Tree $tree, string $gedcom): GedcomRecord
    {
        if (preg_match('/^0 @@ ([_A-Z]+)/', $gedcom, $match) !== 1) {
            throw new InvalidArgumentException('GedcomRecord::createRecord(' . $gedcom . ') does not begin 0 @@');
        }
        $t_id   = $tree->id();

        $xref   = Registry::xrefFactory()->make($match[1]);
        $gedcom = substr_replace($gedcom, $xref, 3, 0);

        // Create a change record
        $today = strtoupper(date('d M Y'));
        $now   = date('H:i:s');
        $gedcom .= "\n1 CHAN\n2 DATE " . $today . "\n3 TIME " . $now . "\n2 _WT_USER " . Auth::user()->userName();

        // Create a pending change
        DB::table('change')->insert([
            'gedcom_id'  => $t_id,
            'xref'       => $xref,
            'old_gedcom' => '',
            'new_gedcom' => $gedcom,
            'status'     => 'pending',
            'user_id'    => Auth::id(),
        ]);

        // Accept this pending change
        $record = Registry::gedcomRecordFactory()->new($xref, $gedcom, null, $tree);

        $pending_changes_service = app(PendingChangesService::class);
        assert($pending_changes_service instanceof PendingChangesService);

        $pending_changes_service->acceptRecord($record);

        return $record;

    }

    public function testTAG(Tree $tree, string $note_txt, string $activeTAG): ?ResponseInterface
    {
        if ($note_txt == $activeTAG) {
            return response([
                'value' => '',
                'text'  => 'Invalid expression',
                'html'  => view('modals/error', [
                    'title' => I18N::translate('There is a problem ...'),
                    'error' => I18N::translate('Only prefix with no content specified'),
                ]),
            ]);
        }
        if (!str_starts_with($note_txt, $activeTAG)) {
            return response([
                'value' => '',
                'text'  => 'Invalid expression',
                'html'  => view('modals/error', [
                    'title' => I18N::translate('There is a problem ...'),
                    'error' => I18N::translate('The tag must start with %s', $activeTAG),
                ]),
            ]);
        }

        $notes          = $this->get_AllNotes($tree)->toArray();            // get all notes

        foreach ($notes as $idx => $note) {                                 // initialize TagsActs with relevant notes
            $_xref      = $note->xref();
            $_tagTxt    = $note->getNote();
            if ($_tagTxt == $note_txt) {
                return response([
                    'value' => '',
                    'text'  => 'Invalid expression',
                    'html'  => view('modals/error', [
                        'title' => I18N::translate('There is a problem ...'),
                        'error' => I18N::translate("The tag '%s' is already in use", $_tagTxt),
                    ]),
                ]);
                }
        }


        // TAG seems to be OK, go on
        return null;
    }

}
