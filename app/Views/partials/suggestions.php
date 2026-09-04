<?php
$suggestionsPayload = isset($suggestions['hierarchy'])
    ? [
        'clients' => array_keys($suggestions['hierarchy']),
        'hierarchy' => $suggestions['hierarchy'],
        // Keep the real Task records used by the Daily Log task picker.
        'tasks' => $suggestions['tasks'] ?? [],
    ]
    : ['clients' => $suggestions['clients'] ?? []];

foreach (['projectsOnly', 'clientsOnly'] as $extraKey) {
    if (isset($suggestions[$extraKey])) {
        $suggestionsPayload[$extraKey] = $suggestions[$extraKey];
    }
}
?>
<script>
    window.__pmsSuggestions = <?= json_encode($suggestionsPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
