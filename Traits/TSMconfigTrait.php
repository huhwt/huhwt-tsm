<?php

/**
 * webtrees - tagging service manager
 * 
 * Copyright (C) 2023 EW.Heinrich
 * 
 * Coding for the configuration in Admin-Panel goes here
 */

declare(strict_types=1);

namespace HuHwt\WebtreesMods\TaggingServiceManager\Traits;

use Throwable;

use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

trait TSMconfigTrait {

    use TSMdatabaseActions;

    /**
     * Default Tag Action
     */
    public const TAG_ACTION_DEFAULT = "TAG";

    /**
     * Domain Tag Actions
     *
     * @return array<int,string>
     */
    public function TAGconfigOptions(): array
    {
        if (!method_exists($this, 'getMname')) {
            $TAGoption_stock    = $this->getPreference('TAG_option_stock','TAG');                       // default - called by instance of AbstractModule
        } else {
            $TAGoption_stock    = $this->getPreferenceNamed($this->getMname(), 'TAG_option_stock','TAG');   // trait called by custom class
        }

        $tCOs               = explode(',', $TAGoption_stock);

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
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        $tagConfigOptions = $this->TAGconfigOptions();
        $tagOptions_stock = $this->getPreference('TAG_option_stock','TAG');

        return $this->viewResponse($this->name() . '::settings', [
            'TAGoption'         => (int) $this->getPreference('TAG_Option', '0'),
            'TAG_options'       => $tagConfigOptions,
            'TAG_options_stock' => $tagOptions_stock,
            'title'             => I18N::translate('Tagging preferences') . ' — ' . $this->title(),
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {

        $TAGoption_stock    = Validator::parsedBody($request)->string('TAG_options_stock');

        if ($TAGoption_stock == '') {
            FlashMessages::addMessage(I18N::translate("There must be at least 1 clause!."), "warning");
            return redirect($this->getConfigLink());
        }

        $_tagCOs    = explode(',', $TAGoption_stock);
        $tagCOtest  = '';
        foreach( $_tagCOs as $_tagCO ) {
            $tagCO  = trim($_tagCO);
            if ( $tagCO > '') {
                if ( str_contains($tagCOtest, $tagCO)) {
                    FlashMessages::addMessage(I18N::translate('The clause "%s" is included more than once!.', $tagCO), 'danger');
                    return redirect($this->getConfigLink());
                } else {
                    $tagCOtest = $tagCOtest . $tagCO . ',';
                }
            }
        }
        $TAGoption_stock    = $tagCOtest;

        $TAGoption          = Validator::parsedBody($request)->integer('TAGoption');

        $this->setPreference('TAG_Option_stock', (string) $TAGoption_stock);

        $this->setPreference('TAG_Option', (string) $TAGoption);

        $_message           = I18N::translate('The preferences for the module “%s” have been updated.', $this->title());
        $_message           .= ": Index->" . (string) $TAGoption . " Text->" . $TAGoption_stock[$TAGoption];
        FlashMessages::addMessage($_message, 'success');

        return redirect($this->getConfigLink());
    }

    /**
     * Domain Tag Actions
     *
     * @return array<int,string>
     */
    public function TAGconfigOptionsNamed(string $M_name): array
    {

        $TAGoption_stock    = $this->getPreferenceNamed($M_name, 'TAG_option_stock','TAG');

        $tCOs               = explode(',', $TAGoption_stock);

        $tagCOtest = [];
        foreach( $tCOs as $tCO ) {
            if ( $tCO > '') {
                $tagCOtest[] = $tCO;
            }
        }

        return $tagCOtest;
    }



}