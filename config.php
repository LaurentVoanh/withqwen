<?php

define('MISTRAL_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions');
define('MISTRAL_MODEL', 'pixtral-12b-2409');
define('MISTRAL_KEYS', array_filter(explode(',', getenv('MISTRAL_KEYS') ?: ' your api key here , your api key here , your api key here ')));

?>