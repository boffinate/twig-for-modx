<?php

declare(strict_types=1);

if (class_exists(\ModxPro\PdoTools\Parsing\Parser::class)) {
    class_alias(\ModxPro\PdoTools\Parsing\Parser::class, 'Boffinate\Twig\ParserBase');
} else {
    class_alias(\MODX\Revolution\modParser::class, 'Boffinate\Twig\ParserBase');
}
