#!/bin/bash
#
# Copyright Magmodules.eu. All rights reserved.
# See COPYING.txt for license details.
#
# E2E test environment configuration for the BOXO module.
# Run inside the Magento container before executing the suite.
#

set -e

# Disable 2FA
if grep -q Magento_TwoFactorAuth "app/etc/config.php"; then
    bin/magento module:disable Magento_TwoFactorAuth -f
fi

# Disable admin captcha and form keys so specs can navigate admin URLs directly
bin/magento config:set admin/captcha/enable 0
bin/magento config:set admin/security/use_form_key 0
bin/magento config:set admin/security/admin_account_sharing 1
bin/magento config:set admin/security/session_lifetime 86400

# --- BOXO ---
# The module ships disabled; the suite needs it on.
bin/magento config:set magmodules_boxo/general/active 1

# Debug logging on: when a spec fails, boxo-debug.log is the first place to look, and turning it
# on afterwards cannot recover what the failing run would have written.
bin/magento config:set magmodules_boxo/debug/enable 1

# The API key and the base URL override are set by BoxoMock.ensureRunning() rather than here, so a
# spec that drives the mock cannot silently run against a stale value this script wrote.

bin/magento cache:flush
