/*
 * webtrees - tagging service manager
 *
 * Copyright (C) 2024 huhwt. All rights reserved.
 *
 * webtrees: online genealogy / web based family history software
 * Copyright (C) 2023 webtrees development team.
 *
 * This is the client side of tagging functions
 * 
 * --------------------------------------------------------------
 * 
 * Functions and Variables prefixed with AT -> working on / regarding elements in the 'Active Tags' region of screen
 * 
 * Functions and Variables prefixed with RT -> working on / regarding elements in the 'Records Tables' region of screen
 * 
 * Functions prefixed with TSM              -> externally referenced in *.phtml
 * 
 * Functions prefixed with __               -> context free
 * 
 */

var TagsActions         = [];       // list of all TagsActions - Index 0 carries the I18N::translated for not yet assigned TAG
var TagsAction_actON    = '';       // the actually clicked TagsActions
var TagsAction_actOFF   = '';       // the actually clicked TagsActions
var TagsAction_act      = '';       // all clicked / highlighted TagsActions
var TagsAction_actColor = '';       // correspondig colors
var TagsAction_actCount = 0;        // counter of highlihted TagsActions
var TagsAction_none     = '';       // the translated '(none)'-text

var TSMcolorACT         = '';
var TSMcolorOFF         = '';

var saveEnabled         = false;    // initial state of TSMbtnSave is disabled

var s_wt                = window.webtrees;                              // grep the webtrees js standard object
var b_wt                = window.bootstrap;

/**   AT...               ActiveTag Actions Region                      */
/**       b                  tbody                                      */
/**       h                  header                                     */
/**   ATua                actions to performe              (upper area) */
/**   ATlb.               table 'Active TagsActions'       (left box)   */
/**   ATrb.               table 'TSM-TagsActions' records  (right box)  */
/**   RT...               RecordsTables Region                          */
const ATfloatMaster     = document.getElementById('TSMfloat');          // Toggle floating the whole region
const ATfloatHeader     = ATfloatMaster.getElementsByTagName('h4')[0];  // Header 'Performed actions to fill the tags' -> target for click

const ATua_TagMaster    = document.getElementById('TSMactTag');         // Perform choose 'Active Tag'
const ATua_TagHeader    = ATua_TagMaster.getElementsByTagName('h6')[0]; // Header 'Active tag' -> placeholder
const ATua_btn_aTag     = document.getElementById('TSMbtnATag');        // input 'Active tag' -> target for click
const ATua_btn_Save     = document.getElementById('TSMbtnSave');        // Save to database
const ATua_btn_Redo     = document.getElementById('TSMbtnRedo_DO');     // Redo (reload Window) -> target for click

const ATlbb_tagsAction  = document.getElementById('tagsAction');        // 1. element of tagsActions ...
const ATlbb_TAtext_empty    = ATlbb_tagsAction.innerHTML;               // ... we need the text as indicator for empty state
const AT_ShowXXX_id         = 'TSMshowXXX';
const AT_ShowXXX            = document.getElementById(AT_ShowXXX_id);   // the ShowBox controlling element
const AT_ShowAll_id         = 'TSMshowAll';
const AT_ShowActive_id      = 'TSMshowActive';
const AT_HideActive_id      = 'TSMhideActive';
const ATlbh_btn_ShowAll     = document.getElementById(AT_ShowAll_id);   // Control - Show all records
const ATlbh_btn_ShowActive  = document.getElementById(AT_ShowActive_id);// Control - Show only records for active tagsAction
const ATlbh_btn_HideActive  = document.getElementById(AT_HideActive_id);// Control - Hide the records with active tagsAction

const ATrbb_TSMactTags      = document.getElementById('TSMtaBody');     // For srolling

const DialogToSave          = document.getElementById('TSMdialTagSave');// For Dialog 'Tags-To-Save'
var TTSid                   = 0;
const TTSactions            = [
    [ 'dummy',      'dummy'         ],
    [ 'TSMbtnATag', 'TSMbtnATag_DO' ],
    [ 'btnAddNote', 'btnAddNote_DO' ],
    [ 'TSMbtnRedo', 'TSMbtnRedo_DO' ],
]

/**
 * For performance reasons, the table contents are initially hidden when the page is accessed.
 * Once the structure of the tables is complete, the contents are displayed.
 * While the build is in progress, the 'prepInfo' element is initially displayed.
 */
function TSM_showTables() {
    let elems = document.getElementsByClassName('wt-facts-table');
    for ( const elem of elems ) {
        let hevis = elem.getAttribute('style');
        if (hevis == 'display:none')
            elem.removeAttribute('style');
    }
    let elem = document.getElementById('prepInfo');
    if ( elem ) {
        elem.setAttribute('style', 'display:none');
    }

}

/**
 * Save a form using ajax, for use in modals
 * webtrees standard call for this ... own construct didn't work :-(
 * 
 * is called as a inline script from TSMchoose-ActiveTag.phtml
 *
 * @param {Event} event
 */
function TSM_createATagModalSubmit(event) {

    event.preventDefault();
    var evt = event.target;
    let eam = document.getElementById("wt-ajax-modal");
    var emc = eam.querySelector(".modal-content");
    //   , a = document.getElementById(r.dataset.wtSelectId);
    s_wt.httpPost(evt.action, new FormData(evt)).then((function(e) {
            return e.json();
        }
    )).then((function(t) {
            emc.innerHTML = t.html;
            let emb = emc.getElementsByClassName('modal-footer')[0];
            emb.addEventListener('click', event => {
                location.reload();
            });
        }
    )).catch((function(e) {
            emc.innerHTML = e;
        }
    ))
}

/**
 * Some areas of the tables will have to be provided with 'click' events ...
 */
function TSM_prepPevents() {
    let Th_elems = document.getElementsByClassName('TSM_Theader');
    for ( const th_elem of Th_elems ) {
        if (!th_elem.classList.contains('tsm-thead')) {
            if (th_elem.hasAttribute('name')) {
                th_elem.addEventListener( 'click', event => {
                    let elemev = event.target;
                    RT_toggleCollapse(elemev);
                });
            }
        }
    }

    let Ft_elems = document.getElementsByClassName('TSM-facts-table');
    for ( const Ft_elem of Ft_elems ) {
        let Id_elems = document.getElementsByClassName('wt-icon-delete');
        for ( const Id_elem of Id_elems ) {
            Id_elem.parentNode.addEventListener( 'click', event => {
                // clickXRdelete(event);
            });
        }
    }
    let Xr_elems = document.getElementsByClassName('TSM_xref');
    for ( const xr_elem of Xr_elems ) {
        xr_elem.addEventListener( 'click', event => {
            let elemev = event.target;
            RT_toggleTAaction(elemev);
        });
    }

    ATua_btn_Save.addEventListener( 'click', event => {
        ATua_clickTAsave(event);
    });

    ATua_btn_Redo.addEventListener( 'click', event => {
        ATua_clickWRedo(event);
    });

    AT_toggleFloating();

    ATlb_prepEvents();

    ATrb_prepEvents();
}

/**
 * Prepare the Actions-View-Region for floating
 */
function AT_toggleFloating() {

    let dragable = document.getElementById('TSMfloat');
    let dragzone = document.getElementById('TSMfloat_drag');
    let TSMfloatHidden = document.getElementsByName('TSMfloatHidden');

    ATfloatHeader.addEventListener( 'click', event => {
        if (!event.target == ATfloatHeader)
            return;
        let Btn_aTag_h6 = ATua_btn_aTag.nextElementSibling;
        if (!ATfloatMaster.classList.contains('TSMfloat')) {
            ATfloatMaster.classList.toggle('TSMfloat');
            let dtitle = dragzone.getAttribute('dtitle');
            dragzone.classList.remove('hiddenLine');
            dragzone.setAttribute('title', dtitle);
            ATrbb_TSMactTags.setAttribute('style', 'width: 100%');
            AT_drag_start(dragable, dragzone);
            ATua_TagMaster.classList.toggle('reactive');
            ATua_btn_aTag.classList.add('hiddenLine');
            Btn_aTag_h6.classList.remove('hiddenLine');
        } else {
            ATfloatMaster.removeAttribute('style');
            ATfloatMaster.classList.toggle('TSMfloat');
            ATfloatMaster.scrollIntoView(false);
            dragzone.classList.add('hiddenLine');
            dragzone.removeAttribute('title');
            ATrbb_TSMactTags.removeAttribute('style');
            AT_drag_stop();
            ATua_TagMaster.classList.toggle('reactive');
            ATua_btn_aTag.classList.remove('hiddenLine');
            Btn_aTag_h6.classList.add('hiddenLine');
        }
        for( let _te of TSMfloatHidden ) {
            _te.classList.toggle('hiddenLine');
        }
        event.stopImmediatePropagation();
    });

}

/**
 * toggle style display for complete table
 */
function RT_toggleCollapse(helem) {
    let he_name = helem.getAttribute('name');
    let henames = document.getElementsByName(he_name);
    for ( const henelem of henames) {
        if ( henelem != helem) {
            let hevis = henelem.getAttribute('style');
            if (hevis == 'display: none') {
                henelem.removeAttribute('style');
            } else {
                henelem.setAttribute('style', 'display: none');
            }
        }
    }
}

/**
 * define the events for the ActiveTags Table (left box)
 */
function ATlb_prepEvents() {

    let _showBoxs = document.getElementsByClassName('TSMshow_box');
    for (const _showBox of _showBoxs) {
        _showBox.addEventListener( 'click', event => {
            ATlbh_clickShowBox(event);
        });
    }
    ATlbh_setShowBox_active(AT_ShowAll_id, true);
}

/**
 * define the events for the ActiveTags RecordTable (right box)
 */
function ATrb_prepEvents() {
    ATrbr_prepTAclick();
    ATrbr_prepTAdeferredAction(1,'TSMbtnATag');
    ATrbr_prepTAdeferredAction(2,'btnAddNote');
    ATrbr_prepTAdeferredAction(3,'TSMbtnRedo');
}

/**
 * click events for tags
 */
function ATrbr_prepTAclick() {
    let telems = ATrbb_TSMactTags.getElementsByClassName('wt-icon-tag');    // we collect significant nodes ...
    let ceFirst = true;
    for ( const telem of telems ) {                                             // ... and grep for each:
        let trElem = telem.parentElement.parentElement;                         // -> the superior table-line
        let tbElem = trElem.parentElement;                                      // -> the superior table-body
        let celem = telem.nextElementSibling;                                   // -> the element to receive the event
        let celemt = celem.innerText;                                           // we grep the text ...
        if (ceFirst) {                                                          // the first line holds the I18N::translated text for 
            TagsAction_none = celemt;                                               // '(none)' - we need it for substitutions
        }
        if (celemt.includes('|'))                                               // ... extended text? ...
            celemt = celemt.substring(0, celemt.indexOf('|'));                      // ... cut off extension
        TagsActions.push(celemt);                                               // ... and this is I18N::translated
        if (!ceFirst) {
            // inject HighLighting
            celem.addEventListener( 'click', event => {
                let elemev = event.target;
                event.stopPropagation();
                let elemevt = elemev.innerText;                                     // we grep the text ...
                if (elemevt.includes('|'))                                          // ... extended text? ...
                    elemevt = elemevt.substring(0, elemevt.indexOf('|'));               // ... cut off extension
                ATrbr_clickTAtoggler(tbElem, trElem, elemev, elemevt);              // ... to feed the handler
            });
            // inject CleanUpXREFs
            let pelem = telem.parentElement.parentElement;
            const aelem = pelem.lastElementChild;
            let delem = aelem.firstElementChild;
            delem.addEventListener( 'click', event => {
                ATrbr_clickTAdelete(trElem, celem, celemt, delem);                      // ... to feed the handler
            });
        } else {
            trElem.style.display = 'none';
        }
        ceFirst = false;
    }
}

/**
 * A TagsAction is to be removed - check all XREFs, remove TagsAction from XREF, delete empty XREFs 
 * 
 * trElem       -> the table-row of clicked TagsAction
 * TAelem        -> the clicked TagsAction-element
 * TAtext       -> the text of the TagsAction-element
 * delem        -> the element carrying the AjaxCall-Url
 */
function ATrbr_clickTAdelete(trElem, TAelem, TAtext, delem) {
    let doneHighlight = TAelem.classList.contains('TSMhighlighted');
    let doRefresh = false;
    let XREFs = [];
    // Collect the XREFs with badges containing the clicked TagsAction
    let RTbodies = document.querySelectorAll('table.TSM-facts-table > tbody');      // all tables with records
    for ( const RTbody of RTbodies) {
        let tbadge = RTbody.parentNode.querySelector('table > thead > tr > th > span'); // we need the element that hosts the actual counter ...
        let tbadge_tc = tbadge.nextElementSibling;                                      // ... and also the total-counter
        let tcount = parseInt(tbadge.innerText);                                    // the actual counter
        let tcount_tc = parseInt(tbadge_tc.innerText.substring(1));                 // the total counter - caveat!: leading '/'
        let td_badges = RTbody.querySelectorAll('div.TSMbadge');                     // all tagsAct-badges in table
        for ( const td_badge of td_badges) {
            let ta_sptxt = td_badge.innerText;                                      // tagsAct-defs in badge
            let do_del = ta_sptxt.includes(TAtext);                                 // tagsAct-toDel included?
            if (do_del) {
                let span_td = td_badge.parentElement;                               // badge's parent - contains the tagsAct-defs
                let span_tr = span_td.parentElement;                                // the record-line
                let xref = span_tr.getAttribute('xref');                            // we get the XREF
                span_td.removeChild(td_badge);                                      // remove the badge
                if (!span_td.firstElementChild) {                                   // any other badges remaining? ...
                    RTbody.removeChild(span_tr);                                     // ... no: remove the record-line
                    tcount--;
                    tcount_tc--;
                    if (doneHighlight)
                        doRefresh = true;
                }
                if (!XREFs.includes(xref))                                          // put xref in list
                    XREFs.push(xref);
            }
        }
        tbadge.innerText = tcount.toString();                                       // update the actual counter
        tbadge_tc.innerText = '/ ' + tcount_tc.toString();                             // ... and also the total counter
    }
    RT_execTAdelete(delem, XREFs);

    let trElemp = trElem.parentNode;
    trElemp.removeChild(trElem);
    let trElemo = trElemp.parentNode;
    let trElemoBadge = trElemo.querySelector('span.badge.bg-secondary');
    let tcount = parseInt(trElemoBadge.textContent) - 1;
    trElemoBadge.textContent = tcount.toString();

    if (doRefresh) {
        RT_refreshTR();
    }
}
/**
 * Remove the XREFs carrying the clicked TagsAction
 */
function RT_execTAdelete(delem, XREFs) {
    let tagsAct = delem.getAttribute('tagsact');
    let action = delem.getAttribute('action');
    let route_ajax = delem.getAttribute('data-url');
    let _url = decodeURIComponent(route_ajax);
    if (_url.includes('&amp;')) {
        _url = _url.replace('&amp;','&');
    }
    _url = _url + '&action=' + encodeURIComponent(action) + '&tagsact=' + encodeURIComponent(tagsAct);
    $.ajax({
        url: _url,
        dataType: 'json',
        data: 'xrefs=' + XREFs.join(';'),
        success: function (ret) {
            var _ret = ret;
            __updateCCEcount(_ret);
            return true;
        },
        complete: function () {
//
        },
        timeout: function () {
            return false;
        }
    });
}
/**
 * Update the Records-Counter in the CCE-webtrees-menu
 * - we don't want to do a complete reload
 * - we have all relevant information here in place
 * - so we modify the counter on client-side
 */
function __updateCCEcount(XREFcnt) {
    let TSMmen = document.querySelector('.CCE_Menue');
    let TSMmenBadge = TSMmen.querySelector('span.badge.bg-secondary');
    TSMmenBadge.textContent = ' '  + XREFcnt.toString() + ' ';
}

/**
 * Toggle highlighting all elements carrying the same text as the clicked TagsAction-Line
 * 
 * structure elements tbody 'TSM-TagsActions'
 * ATtbElem     -> the tbody itself
 * ATtrElem     -> the table-row of clicked 
 * ATelemev     -> the clicked tagsAction
 * ATelemevt    -> the correspondig text
 */
function ATrbr_clickTAtoggler(ATtbElem, ATtrElem, ATelemev, ATelemevt) {
    let doneHighlight = ATelemev.classList.contains('TSMhighlighted');
    let doColor = doneHighlight ? 'OFF' : 'ON';

    let TSMcolgets = ATgetTSMcolor(ATtbElem, ATtrElem, doColor);
    if (typeof(TSMcolgets) == 'string')
        return;
    
    TSMcolorACT = TSMcolgets[0];
    TSMcolorOFF = TSMcolgets[1];
    let colorsOnCnt = TSMcolgets[2];

    let do_esel = false;
    ATlbb_tagsAction.setAttribute('activetag','');
    if (doColor == 'ON') {                                              // we have clicked an inactive tag ...
        if (ATelemevt != TagsAction_none) {                                 // ... and it's not the 'none' tag ...
            TagsAction_actCount++;                                          // ... so we have an NEW ACTIVE TAG!
            TagsAction_act = TagsAction_act + ';' + ATelemevt;              // we store the value ...
            let _tr_color = ATtrElem.getAttribute('color');
            TagsAction_actColor = TagsAction_actColor + ';' + _tr_color;
            TagsAction_actON = ATelemevt;                                     // ... take the value as the default tag ...
            ATlbb_tagsAction.setAttribute('activetag',TagsAction_actON);
            ATlb_createRow(ATlbb_tagsAction);                                 // ... update the element ...
            do_esel = true;
            ATlbb_TAswitchSelect(true);                                   // ... and add the select handler
        } else {
            TagsAction_actON = '';
        }
        ATlbh_enableShowBox();
    } else {                                                            // we have clicked an active tag ...
        TagsAction_actOFF = ATelemevt;                                      // ... we want to know the tag value
        TagsAction_actON = '';                                              // ... it may have been the last one -> deactivate tagging
        if (ATelemevt != TagsAction_none)                                   // ... and it's not the 'none' tag ...
            TagsAction_actCount--;                                              // ... decrease number of active tags
        let _TA_act = [];                                                   // ... set active-tag-structure -> empty
        if (TagsAction_actCount<1) {                                        // ... it has been the last active tag ...
            TagsAction_act = '';                                                // ... deactivate tagging
            TagsAction_actColor = '';
        } else {
            _TA_act     = TagsAction_act.split(';');                        // there are other active tags ...
            let itaCol  = _TA_act.indexOf(ATelemevt);                         // ... so look for the tag to inactivate ...
            if (itaCol >= 0) {
                _TA_act.splice(itaCol, 1);                                      // ... and remove it ...
                let _TA_acol = TagsAction_actColor.split(';');
                _TA_acol.splice(itaCol, 1);
                TagsAction_actColor = _TA_acol.join(';');
            }
            TagsAction_act = _TA_act.join(';');                             // ... and rebuild the structure of active tags
        }
        if (TagsAction_actCount > 0) {                                      // ... and we have active tags ...
            TagsAction_actON = _TA_act[TagsAction_actCount];                    // ... set the last added tag as default tag ...
            ATlbb_tagsAction.setAttribute('activetag',TagsAction_actON);
            ATlb_createRow(ATlbb_tagsAction);                                       // ... update the element ...
            do_esel = true;
            ATlbb_TAswitchSelect(true);                                       // ... and add the select handler
        } else {                                                            // ... no more any active tags
            ATlbb_TAswitchSelect(false);                                    // ... remove the select handler
            ATlbb_tagsAction.innerHTML = ATlbb_TAtext_empty;                       // ... show the state 'inactive' ...
            ATlbb_tagsAction.setAttribute('activetag','');
            do_esel = false;                                                    // ... we don't want any more events for tagging select
            ATlbh_disableShowBox();
        }
    }
    if (do_esel) {                                                      // we have an active select box ...
        let TA_title = ATlbb_tagsAction.getAttribute('title');
        if (!TA_title) {
            TA_title = ATlbb_tagsAction.getAttribute('stitle');
            ATlbb_tagsAction.setAttribute('title', TA_title);
            if ( !ATlbb_tagsAction.classList.contains('TAsel_act')) {
                ATlbb_tagsAction.classList.add('TAsel_act');
                ATlbb_tagsAction.setAttribute('title', TA_title);
            }
        }
    } else {
        if ( ATlbb_tagsAction.classList.contains('TAsel_act')) { ATlbb_tagsAction.classList.remove('TAsel_act'); }
        if ( ATlbb_tagsAction.hasAttribute('title')) { ATlbb_tagsAction.removeAttribute('title'); }
    }

    if (doColor == 'ON') {
        AT_execTAtoggling(ATelemev, ATelemevt, colorsOnCnt, TSMcolorACT);
        // ATlbb_TAsetBolded_do(ATtbElem, false);
        // ATelemev.classList.add('TSMbold');
    } else {
        AT_execTAtoggling(ATelemev, ATelemevt, colorsOnCnt, TSMcolorOFF);
        // ATlbb_TAsetBolded_do(ATtbElem, true);
    }
}

/**
 * manage click for elements with deferred action because off previously checking if there are any valid updates
 * 
 */
function ATrbr_prepTAdeferredAction(_TTSid, elementId) {
    let etest = document.getElementById(elementId);
    etest.addEventListener( 'click', event => {
        TTSid = _TTSid;
        DialogToSave_Action(_TTSid);                      // ... to feed the handler
    });
}

/**
 * perform dialog actions
 * 
 * there are 3 buttons in the dialog
 * 'TTSyes'     -> execute Save and then proceed
 * 'TTSno'      -> don't Save, proceed immediately
 * 'TTSesc'     -> do nothing
 */
function DialogToSave_Action(_TTSid) {
    TTSid           = _TTSid;
    let act_to_perform = TTSactions[TTSid];
    let el_atp      = document.getElementById(act_to_perform[1]);
    let _ret        = false;
    if (!saveEnabled) {
        el_atp.click();
        return _ret;
    }
    let eTTSyes     = document.getElementById('TSM-dTTSyes');
    let eTTSno      = document.getElementById('TSM-dTTSno');
    let eTTSesc     = document.getElementById('TSM-dTTSesc');
    // "Cancel" button closes the dialog without submitting because of [formmethod="dialog"], triggering a close event.
    DialogToSave.addEventListener("close", (e) => {
        let _action = DialogToSave.returnValue;
        if ( _action === 'escape') {
            return null;
        } else if ( _action === 'yes') {
            ATua_clickTAsave_do(ATua_btn_Save, false);
            _ret    = true;
        }
        el_atp.click();
        return _ret;
    });
  
    // Prevent the "confirm" button from the default behavior of submitting the form, and close the dialog with the `close()` method, which triggers the "close" event.
    eTTSyes.addEventListener("click", (event) => {
        event.preventDefault();                     // We don't want to submit this fake form
        DialogToSave.close(eTTSyes.value);          // Have to send the clicked button value here.
    });
    eTTSno.addEventListener("click", (event) => {
        event.preventDefault();                     // We don't want to submit this fake form
        DialogToSave.close(eTTSno.value);          // Have to send the clicked button value here.
    });
    eTTSesc.addEventListener("click", (event) => {
        event.preventDefault();                     // We don't want to submit this fake form
        DialogToSave.close(eTTSesc.value);          // Have to send the clicked button value here.
    });

    DialogToSave.showModal();                      // ... to feed the handler

}


/**
 * Transfer the highlighting to the data lines
 * 
 * ATelemev     -> the clicked tagsAction
 * ATelemevt    -> the corresponding text
 * colorsOnCnt  -> number of active colors
 * trColor      -> the color to toggle
 */
function AT_execTAtoggling(ATelemev, ATelemevt, colorsOnCnt, trColor) {
    ATelemev.classList.toggle('TSMhighlighted');
    ATelemev.classList.toggle(trColor);

    RT_execTAtoggling(ATelemevt, colorsOnCnt, trColor);

}

/**
 * Manage bolding the active tag
 * 
 * ATtbElem     -> the tbody itself
 * doBold       -> Switch   boolean ! string
 *                  false: only remove class TSMbold     true: transfer class TSMbold to another line   
 *                  string: this shall be the new active tag
 */
function ATlbb_TAsetBolded_do(ATtbElem, doBold) {
    function _unBold() {
        let trBold = ATtbElem.querySelector('.TSMbold');                              // do we have an active bolded element? ...
        if (trBold)                                                                 // ... yes! ...
            trBold.classList.toggle('TSMbold');                                         // ... remove bolding
    }

    if (typeof(doBold) == 'boolean') {                                          // we want to shift the bolding to the next active element
        _unBold();
        let _it_lines = ATtbElem.querySelectorAll('tr');                      // grep all TAlines
        for ( const _it_line of _it_lines ) {                                       // ... and grep for each:
            let telem = _it_line.querySelector('div');                              // -> the element carrying the tag information
            if (telem.textContent == TagsAction_actON) {                            // ... and it is the active tag ...
                if (!telem.classList.contains('TSMbold')) {                             // ... and it's not bolded ...
                    telem.classList.add('TSMbold');                                         // ... so add bolding
                }
                break;
            }
        }
    } else if (typeof(doBold) == 'string') {
            if (doBold == TagsAction_actON)
                return;
            _unBold();
            TagsAction_actON = doBold;
            let _it_lines = ATtbElem.querySelectorAll('tr');                  // grep all TAlines
            for ( const _it_line of _it_lines ) {                                       // ... and grep for each:
                let telem = _it_line.querySelector('div');                              // -> the element carrying the tag information
                if (telem.textContent == TagsAction_actON) {                            // ... and it is the active tag ...
                    if (!telem.classList.contains('TSMbold')) {                             // ... and it's not bolded ...
                        telem.classList.add('TSMbold');                                         // ... so add bolding
                        TSMcolorACT = telem.classList[1];                                       // ... and get the color
                    }
                    break;
                }
            }
    }
}

/**
 * Transfer the highlighting to the data lines
 * 
 * ATelemevt    -> the text of the clicked tagsAction
 * colorsOnCnt  -> number of active colors
 * trColor      -> the color to toggle
 */
function RT_execTAtoggling(ATelemevt, colorsOnCnt, trColor) {
    let tbodies = document.querySelectorAll('table.TSM-facts-table > tbody');
    for ( const tbody of tbodies) {
        let trC = 0;                                                        // we want to count badged lines

        let td_badges = tbody.querySelectorAll('div.TSMbadge');
        for ( const td_badge of td_badges) {
            let ta_sptxt = td_badge.innerText;
            if (ta_sptxt.includes(ATelemevt)) {
                td_badge.classList.toggle('TSMhighlighted');
                td_badge.classList.toggle(trColor);
            }
        }

        if (colorsOnCnt > 0) {                                              // we have active highlighting ...
            let tr_lines = tbody.querySelectorAll('tr.TSM_Rline');          // ... so we collect the table-lines carrying gedcom-records ...
            for ( const tr_line of tr_lines) {                              // ... and traverse over it ...
                let tr_text = tr_line.children[1].innerHTML;                // ... the badges are in the middle child ...
                if (tr_text.includes('TSMhighlighted')) {                   // ... if one of them is highlighted ...
                    __toggle_Attribute(tr_line, false, 'hidden');                 // ... the whole line is set visible ...
                    trC++;                                                     // add counter
                } // else 
                    // __toggle_Attribute(tr_line, true, 'hidden');             // ... we don't want to hide unmatched lines
            }
        } else {
            let tr_lines = tbody.querySelectorAll('tr.TSM_Rline');
            for ( const tr_line of tr_lines) {
                __toggle_Attribute(tr_line, false, 'hidden');
                trC++;
            }
        }

        let tbHead = tbody.previousElementSibling;
        let tbHBadge = tbHead.querySelector('span.badge.bg-secondary');
        tbHBadge.textContent = ' '  + trC.toString() + ' ';
    }
}

/**
 * Show all datalines
 */
function RT_execShowAll() {
    let tbodies = document.querySelectorAll('table.TSM-facts-table > tbody');
    for ( const tbody of tbodies) {
        let trC = 0;                                                        // we want to count badged lines

        let td_badges = tbody.querySelectorAll('div.TSMbadge');
        for ( const td_badge of td_badges) {
            if (td_badge.classList.contains('TSMshowActive'))
                td_badge.classList.remove('TSMshowActive');
            if (td_badge.classList.contains('TSMhideActive'))
                td_badge.classList.remove('TSMhideActive');
        }

        let tr_lines = tbody.querySelectorAll('tr.TSM_Rline');          // ... so we collect the table-lines carrying gedcom-records ...
        for ( const tr_line of tr_lines) {                              // ... and traverse over it ...
            __toggle_Attribute(tr_line, false, 'hidden');              // ... the whole line is set visible ...
            trC++;                                                     // add counter
        }

        let tbHead = tbody.previousElementSibling;
        let tbHBadge = tbHead.querySelector('span.badge.bg-secondary');
        tbHBadge.textContent = ' '  + trC.toString() + ' ';
    }
}

/**
 * Show all datalines carrying the clicked tagsAction
 * 
 * ATelemevt    -> the text of the clicked tagsAction
 */
function RT_execShowActive(ATelemevt) {
    let tbodies = document.querySelectorAll('table.TSM-facts-table > tbody');
    for ( const tbody of tbodies) {
        let trC = 0;                                                    // we want to count badged lines

        let td_badges = tbody.querySelectorAll('div.TSMbadge');         // we check all the badges ...
        for ( const td_badge of td_badges) {
            let ta_sptxt = td_badge.innerText;
            if (ta_sptxt.includes(ATelemevt)) {                         // ... if one of is carrying the clicked tagsAction ...
                td_badge.classList.toggle('TSMshowActive');             // ... we set it active
                if (td_badge.classList.contains('TSMhideActive'))       // ... if it also set hidden ...
                    td_badge.classList.toggle('TSMhideActive');             // ... then remove the state hidden
            }
        }

        let tr_lines = tbody.querySelectorAll('tr.TSM_Rline');          // ... so we collect the table-lines carrying gedcom-records ...
        for ( const tr_line of tr_lines) {                              // ... and traverse over it ...
            let tr_text = tr_line.children[1].innerHTML;                // ... the badges are in the middle child ...
            if (tr_text.includes('TSMshowActive')) {                    // ... if one of them is selected ...
                __toggle_Attribute(tr_line, false, 'hidden');              // ... the whole line is set visible ...
                trC++;                                                     // add counter
            } else 
                __toggle_Attribute(tr_line, true, 'hidden');             // ... we don't want to hide unmatched lines
        }

        let tbHead = tbody.previousElementSibling;
        let tbHBadge = tbHead.querySelector('span.badge.bg-secondary');
        tbHBadge.textContent = ' '  + trC.toString() + ' ';
    }
}

/**
 * Hide all datalines carrying the clicked tagsAction
 * 
 * ATelemevt    string      -> the text of the clicked tagsAction
 */
function RT_execHideActive(ATelemevt) {
    let tbodies = document.querySelectorAll('table.TSM-facts-table > tbody');
    for ( const tbody of tbodies) {
        let trC = 0;                                                    // we want to count hidden lines
        let trCa = 0;                                                   // we want to count all lines

        let td_badges = tbody.querySelectorAll('div.TSMbadge');         // we check all the badges ...
        for ( const td_badge of td_badges) {
            let ta_sptxt = td_badge.innerText;
            if (ta_sptxt.includes(ATelemevt)) {                         // ... if one of is carrying the clicked tagsAction ...
                td_badge.classList.toggle('TSMhideActive');                 // ... we hide it
                if (td_badge.classList.contains('TSMshowActive'))       // ... if it also set shown ...
                    td_badge.classList.toggle('TSMshowActive');             // ... then remove the state shown
            }
        }

        let tr_lines = tbody.querySelectorAll('tr.TSM_Rline');          // ... so we collect the table-lines carrying gedcom-records ...
        for ( const tr_line of tr_lines) {                              // ... and traverse over it ...
            trCa++;
            let tr_text = tr_line.children[1].innerHTML;                // ... the badges are in the middle child ...
            if (tr_text.includes('TSMhideActive')) {                    // ... if one of them is selected ...
                __toggle_Attribute(tr_line, true, 'hidden');              // ... the whole line is set visible ...
                trC++;                                                    // add counter
            } else 
                __toggle_Attribute(tr_line, false, 'hidden');             // ... we don't want to show unmatched lines
        }

        let tbHead = tbody.previousElementSibling;
        let tbHBadge = tbHead.querySelector('span.badge.bg-secondary');
        trCa -= trC;
        tbHBadge.textContent = ' '  + trCa.toString() + ' ';
    }
}

/**
 * Create tagsAction Table structure
 */
function ATlb_createRow(el_tagsAction) {
    let _TA_act     = TagsAction_act.split(';');
    let _TA_acol    = TagsAction_actColor.split(';');
    let _td_line = '<tr><td><div>...</div></td></tr>';
    let _TAlen = _TA_act.length;
    let _html = '';
    for (let i=1; i<_TAlen; i++) {
        let _TA_at = _TA_act[i];
        _html += _td_line.replace('...', _TA_at);
    }
    el_tagsAction.innerHTML = _html;
    let _tA_trs = el_tagsAction.querySelectorAll('tr');
    let i = 0;
    for ( let _tA_tr of _tA_trs) {
        i++;
        let _at  = _TA_act[i];
        let _bold = (_at == TagsAction_actON ? ' TSMbold' : '');
        let _col = _TA_acol[i];
        _tA_tr.setAttribute('color',_col);
        let _tA_td = _tA_tr.firstElementChild;
        let _tA_sp = _tA_td.firstElementChild;
        _tA_sp.className = ('TSMhighlighted TSM'+_col + _bold);
    }
}

/**
 * manage eventhandler for TAselect
 * 
 * doHandler    -> true: addHandler     false: remove Handler
 */
function ATlbb_TAswitchSelect(doHandler) {
    if (!ATlbb_tagsAction)
        return;
    if (doHandler) {
        ATlbb_tagsAction.addEventListener('click', ATlbb_TAsetBolded, false);
    } else {
        ATlbb_tagsAction.removeEventListener('click', ATlbb_TAsetBolded, false);
    }
}
function ATlbb_TAsetBolded(ev) {
    let evelem = ev.target;
    let _TAaction = evelem.innerText;
    ATlbb_tagsAction.setAttribute('activetag',_TAaction);
    let ATtbElem = document.querySelector('tbody');
    ATlbb_TAsetBolded_do(ATtbElem, _TAaction);
}

/**
 * Remove XREF from tags
 */
function clickXRdelete(event) {
    let delem   = event.target;
    event.stopImmediatePropagation();
    do {
        if (delem.classList.contains('btn'))
            break;
        delem = delem.parentNode;
    } while (!delem.classList.contains('btn'));
    let trElem  = delem.parentNode.parentNode.parentNode;
    execXRdelete(delem.parentNode);

    let trElemp = trElem.parentNode;
    trElemp.removeChild(trElem);
    let trElemo = trElemp.parentNode;
    let trElemoBadge = trElemo.querySelector('span.badge.bg-secondary');
    let tcount = parseInt(trElemoBadge.textContent) - 1;                    // update the actual counter
    trElemoBadge.textContent = tcount.toString();
    let tbadge_tc = trElemoBadge.nextElementSibling;                        // ... and also the total-counter
    if (tbadge_tc) {                                                        // if there is a total-counter ...
        let tcount_tc = parseInt(tbadge_tc.innerText.substring(1)) - 1;         // ... get the content - caveat!: leading '/'
        tbadge_tc.innerText = '/ ' + tcount_tc.toString();                      // ... and set new value
    }
}
function execXRdelete(delem) {
    let xref = delem.getAttribute('xref');
    let action = delem.getAttribute('action');
    let route_ajax = delem.getAttribute('data-url');
    let _url = decodeURIComponent(route_ajax);
    if (_url.includes('&amp;')) {
        _url = _url.replace('&amp;','&');
    }
    _url = _url + '&action=' + encodeURIComponent(action) + '&xref=' + encodeURIComponent(xref);
    $.ajax({
        url: _url,
        // dataType: 'json',
        // data: 'xrefs=' + XREFs.join(';'),
        success: function (ret) {
            var _ret = ret;
            __updateCCEcount(_ret);
            return true;
        },
        complete: function () {
//
        },
        timeout: function () {
            return false;
        }
    });

}
/**
 * add/remove the active tag in records tables
 */
function RT_toggleTAaction(elemev) {
    function _addTAction(elemev, td_badge0) {
        let td_badgex = td_badge0.cloneNode();
        td_badgex.innerText = ' ' + TagsAction_actON;
        if (!td_badgex.classList.contains('TSMhighlighted'))
            td_badgex.classList.add('TSMhighlighted');
        let _cname = td_badgex.className;
        let _xcolor_i = _cname.indexOf('TSMcolor');
        if (_xcolor_i >= 0) {
            let _xcolor = _cname.substring(_xcolor_i, _xcolor_i+9);
            td_badgex.classList.toggle(_xcolor);
        }
        td_badgex.classList.toggle(TSMcolorACT);
        elemev.appendChild(td_badgex);
        AT_testSaveBtn();
    }

    if (TagsAction_actON == '')
        return;

    let td_badges    = elemev.querySelectorAll('div.TSMbadge');
    if ( td_badges.length == 0) {                               // oops - we have clicked a div
        elemev = elemev.parentNode;                             // but we need the td.TSM_xref
        td_badges    = elemev.querySelectorAll('div.TSMbadge');
    }
    let badgeCount = 0;
    let td_badge0 = td_badges[0];
    if ( (td_badges.length == 1) ) {
        let ta_sptxt = td_badge0.innerText;
        if (ta_sptxt == TagsAction_none) {                      // there hasn't been a valid tag by now
            badgeCount++;                                       // the number of active tags has to be increased
            td_badge0.innerText = TagsAction_actON;
            td_badge0.classList.toggle('TSMhighlighted');
            td_badge0.classList.toggle(TSMcolorACT);
            AT_testSaveBtn();
        } else if (ta_sptxt == TagsAction_actON) {              // the only tag is the active tag - set it back to '(none)'
            badgeCount--;                                       // the number of active tags has to be decreased
            td_badge0.innerText = TagsAction_none;
            td_badge0.classList.toggle('TSMhighlighted');
            td_badge0.classList.toggle(TSMcolorACT);
            AT_testSaveBtn();
        } else {                                                // there is actually an other tag - we have to add the active tag
            badgeCount++;                                       // the number of active tags has to be increased
            _addTAction(elemev, td_badge0);
        }
    } else {
        ita = -1;
        for ( let it = 0; it < td_badges.length; it++) {
            let td_badge = td_badges[it];
            let ta_sptxt = td_badge.innerText;
            if (ta_sptxt.includes(TagsAction_actON)) {
                ita = it;
                break;
            }
        }
        if (ita < 0) {
            badgeCount++;                                       // the number of active tags has to be increased
            _addTAction(elemev, td_badge0);
        } else {
            badgeCount--;                                       // the number of active tags has to be decreased
            let td_badged = td_badges[ita];
            elemev.removeChild(td_badged);
            AT_testSaveBtn();
        }
    }
    let tbody     = elemev.parentNode.parentNode;                               // we locate the actual counter ...
    let tbadge = tbody.parentNode.querySelector('table > thead > tr > th > span');  // we need the element that hosts the actual counter ...
    let tcount = parseInt(tbadge.innerText);                                    // the actual counter
    tcount += badgeCount;
    tbadge.innerText = tcount.toString();                                       // update the actual counter
}

/**
 * Test if 'TSMbtnSave' is enabled
 */
function AT_testSaveBtn() {
    if (saveEnabled)
        return;
    if (ATua_btn_Save.hasAttribute('disabled'))
        ATua_btn_Save.removeAttribute('disabled');
    saveEnabled = true;
}

/**
 * 'TSMshow_box' switch disabled/enabled 
 */
function ATlbh_enableShowBox() {
    let _showBoxs = document.getElementsByClassName('TSMshow_box');
    for (const _showBox of _showBoxs) {
        __toggle_Attribute(_showBox, false, 'disabled');
    }
}
/**
 * 'TSMshow_box' switch disabled/enabled 
 */
function ATlbh_disableShowBox() {
    let _showBoxs = document.getElementsByClassName('TSMshow_box');
    for (const _showBox of _showBoxs) {
        __toggle_Attribute(_showBox, true, 'disabled');
    }
    let attrSB = AT_ShowXXX.getAttribute('actsb');
    let attrShow = AT_ShowXXX.getAttribute('acttashow');
    let attrHide = AT_ShowXXX.getAttribute('acttahide');
    if (attrShow > '')
        AT_ShowXXX.setAttribute('acttashow','');
    if (attrHide > '')
        AT_ShowXXX.setAttribute('acttahide','');
    ATlbh_setShowBox_active(AT_ShowActive_id, false);                   // switch Show OFF
    ATlbh_setShowBox_active(AT_HideActive_id, false);                   // switch Hide OFF
    ATlbh_setShowBox_active(AT_ShowAll_id, true);                       // switch ShowAll ON
}

/**
 * 'TSMshow_box' Click event
 */
function ATlbh_clickShowBox(event) {
    let el = event.target;
    event.stopImmediatePropagation();
    if (TagsAction_actON == '')
        return;
    do {
        if (el.classList.contains('btn'))
            break;
        el = el.parentNode;
    } while (!el.classList.contains('btn'));

    let SBid = el.id;
    if (!el.classList.contains('active-box')) {
        switch(SBid) {
            case AT_ShowAll_id:
                ATlbh_execShowAll();
                break;
            case AT_ShowActive_id:
                ATlbh_execShowActive(true);
                break;
            case AT_HideActive_id:
                ATlbh_execHideActive(true);
                break;
            }
    } else {
        switch(SBid) {
            case AT_ShowAll_id:
                // 'showAll' will be toggled by other actions
                break;
            case AT_ShowActive_id:
                ATlbh_execShowActive(false);
                break;
            case AT_HideActive_id:
                ATlbh_execHideActive(false);
                break;
            }
    }
}

/**
 * perform 'TSMshowAll' actions
 */
function ATlbh_execShowAll() {
    ATlbh_setShowBox_active(AT_ShowAll_id, true);

    RT_execShowAll();

    let attrSB = AT_ShowXXX.getAttribute('actsb');
    if (attrSB.includes(AT_ShowActive_id))
        ATlbh_setShowBox_active(AT_ShowActive_id, false);
    if (attrSB.includes(AT_HideActive_id))
        ATlbh_setShowBox_active(AT_HideActive_id, false);
    
    AT_ShowXXX.setAttribute('acttashow','');

    AT_ShowXXX.setAttribute('acttahide','');
}
/**
 * perform 'TSMshowActive' actions
 * 
 * doBox        boolean     true: Show the elements with active tag     false: UnShow the elements with active tag
 */
function ATlbh_execShowActive(doBox) {

    function _execShow(_tagsAction_actDO) {
        ATlbh_setShowBox_active(AT_ShowActive_id, true);
        // test others
        if (attrSB.includes(AT_ShowAll_id)) {
            attrSB = __clean_Attribute(attrSB, AT_ShowAll_id);                  // switch ShowAll OFF
            ATlbh_setShowBox_active(AT_ShowAll_id, false);
        }
        if (attrHide.includes(_tagsAction_actDO)) {                             // if same tag actually hidden ...
            attrHide = __clean_Attribute(attrHide,_tagsAction_actDO);                // ... clean ...
            AT_ShowXXX.setAttribute('acttahide',attrHide);                               // ... and restore
            if ( attrHide == '') {                                                  // if there are any other hidden tags ...
                ATlbh_setShowBox_active(AT_HideActive_id, false);                       // switch Hide OFF
            }
        }
        attrShow = __add_Attribute(attrShow, _tagsAction_actDO);                // ad active tag ...
        AT_ShowXXX.setAttribute('acttashow',attrShow);                              // ... and restore
    }

    let attrSB = AT_ShowXXX.getAttribute('actsb');
    let attrShow = AT_ShowXXX.getAttribute('acttashow');
    let attrHide = AT_ShowXXX.getAttribute('acttahide');

    RT_execShowActive(TagsAction_actON);

    if ( doBox ) {                                                          // Show active tag
        _execShow(TagsAction_actON);
        return;
    }                                                                // UnShow active tag
    if (attrShow.includes(TagsAction_actON)) {                              // it's a previously shown action: UnShow it
        attrShow = __clean_Attribute(attrShow, TagsAction_actON);               // clean ...
        AT_ShowXXX.setAttribute('acttashow',attrShow);                              // ... and restore
        if ( attrShow == '') {                                                  // if there are no more other tags to show ...
            ATlbh_setShowBox_active(AT_ShowActive_id, false);                   // switch Show OFF
            if ( attrHide == '' ) {                                             // if there are also no tags hidden ...
                ATlbh_setShowBox_active(AT_ShowAll_id, true);                       // switch ShowAll ON
                RT_execShowAll();
                return;
            }
        }
        // RT_execShowActive(TagsAction_actON);
        // return;
    }
}
/**
 * perform 'TSMhideActive' actions
 * 
 * doBox        boolean     true: Hide the elements with active tag anyway    false: Test which element to UnHide
 */
function ATlbh_execHideActive(doBox) {

    function _execHide(_tagsAction_actDO) {
        ATlbh_setShowBox_active(AT_HideActive_id, true);
        // test others
        if (attrSB.includes(AT_ShowAll_id)) {
            attrSB = __clean_Attribute(attrSB, AT_ShowAll_id);                  // switch ShowAll OFF
            ATlbh_setShowBox_active(AT_ShowAll_id, false);
        }
        if (attrShow.includes(_tagsAction_actDO)) {                              // if same tag actually shown ...
            attrShow = __clean_Attribute(attrShow,_tagsAction_actDO);                // ... clean ...
            AT_ShowXXX.setAttribute('acttashow',attrShow);                               // ... and restore
            if ( attrShow == '') {                                                  // if there are no other shown tags ...
                ATlbh_setShowBox_active(AT_ShowActive_id, false);                       // switch Show OFF
            }
        }
        attrHide = __add_Attribute(attrHide, _tagsAction_actDO);                 // ad active tag ...
        AT_ShowXXX.setAttribute('acttahide',attrHide);                              // ... and restore
        RT_execHideActive(_tagsAction_actDO);
    }

    let attrSB = AT_ShowXXX.getAttribute('actsb');
    let attrShow = AT_ShowXXX.getAttribute('acttashow');
    let attrHide = AT_ShowXXX.getAttribute('acttahide');

    if ( doBox ) {                                                          // Hide active tag anyway - there has not been hiding before
        _execHide(TagsAction_actON);
        return;
    }
    /** doBox -> false      there has been a Hide action before
     *                      we have to check if we have to do UnHide or to Hide another tag
     */
    if (attrHide.includes(TagsAction_actON)) {                              // it's a previously hidden action: UnHide it
        attrHide = __clean_Attribute(attrHide, TagsAction_actON);               // clean ...
        AT_ShowXXX.setAttribute('acttahide',attrHide);                              // ... and restore
        if ( attrHide == '') {                                                  // if there are no other tags to hide ...
            ATlbh_setShowBox_active(AT_HideActive_id, false);                   // switch Show OFF
            if ( attrShow == '' ) {                                             // if there are also no tags shown ...
                ATlbh_setShowBox_active(AT_ShowAll_id, true);                       // switch ShowAll ON
                RT_execShowAll();
                return;
            }
        }
        RT_execHideActive(TagsAction_actON);
        return;
    }
    /** There has been Hiding before but on other tags, so we have to do Hiding the active tag */
    _execHide(TagsAction_actON);
}

/**
 * 'TSMshow_box*' Set class 'active-box'
 * 
 * SBid         string      DOM id of targeted ShowBox
 * set_active   boolean     classlist 'active-box'  -> true: add    false: remove
 */
function ATlbh_setShowBox_active(SBid, set_active) {
    let SBel = document.getElementById(SBid);
    if (set_active && !SBel.classList.contains('active-box'))
        SBel.classList.add('active-box');
    if (!set_active && SBel.classList.contains('active-box'))
        SBel.classList.remove('active-box');
    let actSB = AT_ShowXXX.getAttribute('actSB');
    if (set_active) {
        actSB = __add_Attribute(actSB, SBid);
    } else {
        actSB = __clean_Attribute(actSB, SBid);
    }
    AT_ShowXXX.setAttribute('actSB', actSB);
    return SBel;
}

/**
 * element      DOMelement
 * doState      boolean     -> true: set attribute    false: remove attribute
 * _attribute   string      attribute's name
 */
function __toggle_Attribute(element, doState, _attribute) {
    if (doState) {
        if (!element.hasAttribute(_attribute)) { element.setAttribute(_attribute, true); }
    } else {
        if (element.hasAttribute(_attribute)) { element.removeAttribute(_attribute); }
    }
}

/**
 * attrString   string      the attribute string from which we want to remove a part
 * attrVal      string      the part that has to be removed
 * 
 * return       string      the new state of attribute string ('' or '1part' or 'part1;part2;...;)
 */
function __clean_Attribute(attrString, attrVal) {
    let aS = attrString;
    if (attrString.includes(attrVal)) {
        let _aS_o = attrString.split(';');
        let _aS_n = [];
        _aS_o.forEach(val => {
            if (val != attrVal)
                _aS_n.push(val);
        });
        let _aSl = _aS_n.length;
        if (_aSl == 0) {
            aS = '';
        } else if (_aSl == 1) {
            aS = _aS_n[0];
        } else {
            aS = _aS_n.join(';');
        }
    }
    return aS;

}
/**
 * attrString   string      the attribute string to which we want to add a part
 * attrVal      string      the part that has to be added
 * 
 * return       string      the new state of attribute string ('1part' or 'part1;part2;...;)
 */
function __add_Attribute(attrString, attrVal) {
    let aS = attrString;
    let _aS_n = [];
    if (!attrString.includes(attrVal)) {
        if (attrString > '')
            _aS_n = attrString.split(';');
        _aS_n.push(attrVal);
        let _aSl = _aS_n.length;
        if (_aSl == 0) {
            aS = '';
        } else if (_aSl == 1) {
            aS = _aS_n[0];
        } else {
            aS = _aS_n.join(';');
        }
    }
    return aS;

}

/**
 * Toggle highlighting all elements carrying the same text as the clicked TagsAction-Line
 * 
 * ATtbElem     -> the tbody itself
 * ATtrElem     -> the table-row of clicked 
 * colorDo      -> Switch Highlighting - 'ON'/'OFF'
 */
function ATgetTSMcolor(ATtbElem, ATtrElem, colorDo) {
    let colorsOn = ATtbElem.getAttribute('colorsOn');
    colOn = [];
    if (colorsOn > '')
        colOn = colorsOn.split(';');
    let colorsOff = ATtbElem.getAttribute('colorsOff');
    let colOff = colorsOff.split(';');
    let colOff_act = colOff.filter((colX) => colX != '_');

    let trColor = ATtrElem.getAttribute('color') ?? '';
    let trColorACT = '';
    let trColorOFF = '';
    if (colorDo == 'ON') {
        if (colOff_act.length == 0) {
            alert(TSMalert_nca);
            return '';
        }
        trColorACT = colOff_act.shift();
        let iCol = parseInt(trColorACT.substring(trColorACT.length-1));
        colOff[iCol] = '_';
        colOn.push(trColorACT);
        ATtrElem.setAttribute('color', trColorACT);
    } else {
        if (colOn.length == 0) {
            alert(TSMalert_ncd);
            return '';
        }
        trColorOFF = trColor;
        let itrCol = colOn.indexOf(trColorOFF);
        if (itrCol >= 0) {
            colOn.splice(itrCol, 1);
            let iCol = parseInt(trColorOFF.substring(trColorOFF.length-1));
            colOff[iCol] = trColorOFF;
            ATtrElem.removeAttribute('color');
        }
        if (colOn.length > 0) {
            trColorACT = colOn[colOn.length-1];
        } else 
            trColorACT = '';
    }
    colorsOn = colOn.join(';');
    ATtbElem.setAttribute('colorsOn', colorsOn);
    colorsOff = colOff.join(';');
    ATtbElem.setAttribute('colorsOff', colorsOff);
    return ['TSM'+trColorACT, 'TSM'+trColorOFF, colorsOn.length];
}

function RT_refreshTR() {
    let tbodies = document.querySelectorAll('table.TSM-facts-table > tbody');
    for ( const tbody of tbodies) {
        let trC = 0;                                                    // we want to count badged lines
        let tr_lines = tbody.querySelectorAll('tr.TSM_Rline');          // ... so we collect the table-lines carrying gedcom-records ...
        for ( const tr_line of tr_lines) {                              // ... and traverse over it ...
            __toggle_Attribute(tr_line, true, 'hidden');                      // ... the whole line is set visible ...
            trC++;                                                      // add counter
        }
        let tbHead = tbody.previousElementSibling;
        let tbHBadge = tbHead.querySelector('span.badge.bg-secondary');
        tbHBadge.textContent = ' '  + trC.toString() + ' ';
     }
}

function AT_drag_start(element, dragzone) {
    let pos1 = 0,
      pos2 = 0,
      pos3 = 0,
      pos4 = 0;

    const dragMouseUp = () => {
        element.onmouseup = null;
        element.onmousemove = null;

        dragzone.classList.remove('drag');
    };

    const dragMouseMove = (event) => {
        event.preventDefault();
        if (!element.classList.contains('TSMfloat'))
            return;

        pos1 = pos3 - event.clientX;
        pos2 = pos4 - event.clientY;
        pos3 = event.clientX;
        pos4 = event.clientY;

        element.style.top = `${element.offsetTop - pos2}px`;
        element.style.left = `${element.offsetLeft - pos1}px`;
    };

    const dragMouseDown = (event) => {
        event.preventDefault();

        pos3 = event.clientX;
        pos4 = event.clientY;

        dragzone.classList.add('drag');

        element.onmouseup = dragMouseUp;
        element.onmousemove = dragMouseMove;
    };

    dragzone.onmousedown = dragMouseDown;
};
function AT_drag_stop() {
    ATfloatMaster.onmouseup = null;
    ATfloatMaster.onmousemove = null;
}

/**
 * Execute 'Save tags to database'
 */
function ATua_clickTAsave(event) {
    let belem   = event.target;
    event.stopImmediatePropagation();
    do {
        if (belem.classList.contains('btn'))
            break;
        belem = belem.parentNode;
    } while (!belem.classList.contains('btn'));


    ATua_clickTAsave_do(belem, true);
}

function ATua_clickTAsave_do(belem, do_reload) {
    let helem = belem.parentNode;
    let XREFs = TSM_XREFcollect();

    let xref = helem.getAttribute('xref');
    let action = helem.getAttribute('action');
    let route_ajax = helem.getAttribute('data-url');
    let _url = decodeURIComponent(route_ajax);
    if (_url.includes('&amp;')) {
        _url = _url.replace('&amp;','&');
    }
    _url = _url + '&action=' + encodeURIComponent(action);
    document.body.classList.add('waiting');
    $.ajax({
        type: 'POST',
        url: _url,
        dataType: 'json',
        data: { xrefs: JSON.stringify(XREFs) },
        success: function (ret) {
            var _ret = ret;
            document.body.classList.remove('waiting');
            if (do_reload) { location.reload(); }
            // __updateCCEcount(_ret);
            return true;
        },
        complete: function () {
            document.body.classList.remove('waiting');
//
        },
        timeout: function () {
            document.body.classList.remove('waiting');
            return false;
        }
    });
}

function TSM_XREFcollect() {
    let XREFs = {};
    let tr_lines = document.querySelectorAll('tr.TSM_Rline');          // ... so we collect the table-lines carrying gedcom-records ...
    for ( const tr_line of tr_lines) {                              // ... and traverse over it ...
        let a_xref = tr_line.getAttribute('xref');
        let l_tags = tr_line.getElementsByClassName('TSMbadge');
        let a_tags = [];
        for ( const l_tag of l_tags ) {
                let l_ttc = l_tag.textContent.replace(/[\n\r]/g, '');
                if (l_ttc.startsWith(' '))
                    l_ttc = l_ttc.trim();
                a_tags.push(l_ttc);
        }
        let tags = '';
        if (a_tags.length == 1)
            tags = a_tags[0];
        if (a_tags.length > 1)
            tags = a_tags.join(';');
        XREFs[a_xref] = tags;
    }
    return XREFs;
}

function ATua_clickWRedo(event) {
    let delem   = event.target;
    event.stopImmediatePropagation();
    do {
        if (delem.classList.contains('btn'))
            break;
        delem = delem.parentNode;
    } while (!delem.classList.contains('btn'));

    // delem = delem.parentNode;

    let route_ajax = delem.getAttribute('data-url');
    let _url = decodeURIComponent(route_ajax);
    if (_url.includes('&amp;')) {
        _url = _url.replace('&amp;','&');
    }
    document.body.classList.add('waiting');

    s_wt.httpGet(_url)
        .then((function(e) {
                return e.json();
            }
        ))
        .then((function(t) {
                document.body.classList.remove('waiting');
                location.reload();
            }
        ))
        .catch((function(e) {
                emc.innerHTML = e;
            }
        ))
}
