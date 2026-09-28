<?php

return [
    // Providing a `sections` key *completely* overwrites the corresponding project config settings!
    // Omit a section to implicitly deny guest creation.
    // 'sections' => [
    //     // Each element in this array should use the following structure:
    //     [
    //         'sectionUid' => '...',
    //         'allowGuestSubmissions' => true,
    //         'enabledByDefault' => true,
    //         'runValidation' => true,
    //         'authorUid' => '...',
    //     ],
    //     // ...
    // ],
    // Set to a positive integer (requests per minute), or `null` to disable rate limiting:
    'rateLimit' => null,
    'entryVariable' => 'entry',
];
