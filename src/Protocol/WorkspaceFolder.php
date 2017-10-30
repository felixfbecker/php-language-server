<?php

namespace LanguageServer\Protocol;

class WorkspaceFolder
{
    /**
	 * The associated URI for this workspace folder.
     *
	 * @var string
	 */
	public $uri;

	/**
	 * The name of the workspace folder. Defaults to the uri's basename.
     *
	 * @var string
	 */
	public $name;
}
