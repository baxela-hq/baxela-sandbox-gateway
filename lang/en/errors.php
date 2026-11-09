<?php

/**
 * Extension error messages. The sandbox driver signals failures
 * through the Payment module's exception codes (webhook_invalid,
 * gateway_unconfigured), which carry that module's translations — so
 * no package-owned codes exist yet. Extensions with their own failure
 * modes register them here; the namespace is the package's own.
 */
return [];
