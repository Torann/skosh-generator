<?php

namespace Skosh\Support;

use ParsedownExtra;

class SkoshParsedown extends ParsedownExtra
{
    protected function blockCodeComplete($Block)
    {
        $Block = parent::blockCodeComplete($Block);

        return $this->escapeBlockCodeText($Block);
    }

    protected function blockFencedCodeComplete($Block)
    {
        $Block = parent::blockFencedCodeComplete($Block);

        return $this->escapeBlockCodeText($Block);
    }

    protected function escapeBlockCodeText($Block)
    {
        $Block['element']['text']['text'] = str_replace(['{', '}'], ['&lbrace;', '&rbrace;'], $Block['element']['text']['text']);

        return $Block;
    }
}