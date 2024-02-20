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
 * Trait TSMcartActions - bundling all actions regarding Session::cart
 */
trait TSMcartActions
{
    /**
     * Get the Xrefs in the clippings cart.
     *
     * @param Tree $tree
     *
     * @return array
     */
    private function getXrefsInCart(Tree $tree): array
    {
        $cart  = Session::get('cart', []);
        $xrefs = $cart[$tree->name()] ?? [];
        return $xrefs;
    }

    /**
     * Get the Xrefs in the clippings cart -> we want solely the XREF-ids in an array.
     *
     * @param Tree $tree
     *
     * @return array
     */
    private function get_CartXrefs(Tree $tree): array
    {
        $cart = Session::get('cart', []);
        $xrefs = array_keys($cart[$tree->name()] ?? []);
        $_xrefs = array_map('strval', $xrefs);           // PHP converts numeric keys to integers.
        return $_xrefs;
    }

    /**
     * There have been XREFs removed in 'tags' - remove them in 'cart' too
     *
     * @param Tree $tree
     *
     */
    private function remove_Cart(Tree $tree, string $xref): void
    {
        $cart = Session::get('cart', []);
        unset($cart[$tree->name()][$xref]);
        Session::put('cart', $cart);

    }
}