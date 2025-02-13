<?php

declare(strict_types=1);

namespace PhpMyAdmin\Controllers\Table\Structure;

use PhpMyAdmin\Controllers\AbstractController;
use PhpMyAdmin\Http\ServerRequest;
use PhpMyAdmin\SqlParser\Context;

use function _ngettext;
use function count;
use function implode;
use function sprintf;
use function trim;

final class ReservedWordCheckController extends AbstractController
{
    public function __invoke(ServerRequest $request): void
    {
        if ($GLOBALS['cfg']['ReservedWordDisableWarning'] !== false) {
            $this->response->setRequestStatus(false);

            return;
        }

        $columns_names = $request->getParsedBodyParam('field_name');
        $reserved_keywords_names = [];
        foreach ($columns_names as $column) {
            if (! Context::isKeyword(trim($column), true)) {
                continue;
            }

            $reserved_keywords_names[] = trim($column);
        }

        if (Context::isKeyword(trim($GLOBALS['table']), true)) {
            $reserved_keywords_names[] = trim($GLOBALS['table']);
        }

        if (count($reserved_keywords_names) === 0) {
            $this->response->setRequestStatus(false);
        }

        $this->response->addJSON(
            'message',
            sprintf(
                _ngettext(
                    'The name \'%s\' is a MySQL reserved keyword.',
                    'The names \'%s\' are MySQL reserved keywords.',
                    count($reserved_keywords_names),
                ),
                implode(',', $reserved_keywords_names),
            ),
        );
    }
}
