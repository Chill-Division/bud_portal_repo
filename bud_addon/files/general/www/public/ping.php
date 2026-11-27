<?php
// BUD's connection check. js/bud.js asks here before it sends a form, and
// when a phone wakes up.
//
// While Home Assistant's session for BUD (its ingress session) has lapsed,
// Home Assistant answers "401: Unauthorized" itself and this file never runs,
// so any answer from here means a form will get through. It doesn't open the
// database, so it stays quick.
header('Cache-Control: no-store');
http_response_code(204);
