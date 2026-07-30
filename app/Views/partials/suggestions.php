<?php
$suggestionsPayload = isset($suggestions['hierarchy'])
    ? ['clients' => array_keys($suggestions['hierarchy']), 'hierarchy' => $suggestions['hierarchy']]
    : ['clients' => $suggestions['clients'] ?? []];
?>
<script>
    window.__pmsSuggestions = <?= json_encode($suggestionsPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
