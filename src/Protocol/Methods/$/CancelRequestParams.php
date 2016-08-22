<?php

namespace LanguageServer\Protocol\Methods;

use LanguageServer\Protocol\Params;

class CancelRequestParams extends Params
{
	/**
	 * The request id to cancel.
     *
     * @var int|string
	 */
	public $id;
}
