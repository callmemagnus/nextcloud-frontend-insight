<?php
/**
 * SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\FrontendInsight\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\Server;

/** @var bool $collect_errors */
/** @var bool $collect_unhandled_rejections */
/** @var string $requesttoken */

$urlGenerator = Server::get(IURLGenerator::class);
$saveUrl = $urlGenerator->linkToRoute(Application::APP_ID . '.Settings.save');
?>

<style>
    /* Save button loading state */
    .fei-save-btn[aria-busy="true"] {
        position: relative;
        opacity: 0.7;
        pointer-events: none;
    }

    .fei-save-btn[aria-busy="true"]::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 14px;
        height: 14px;
        margin: -7px 0 0 -7px;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.6);
        border-top-color: rgba(255, 255, 255, 1);
        animation: fei-spin 0.8s linear infinite;
    }

    @keyframes fei-spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Groups checkbox list styling */
    .fei-groups-list {
        max-height: 320px; /* approx. room for ~10 items without crowding */
        overflow-y: auto;
        padding: 0; /* minimal container padding */
        background-color: var(--color-main-background, #ffffff);
        color: var(--color-main-text, #1f1f1f);
        border: 1px solid var(--color-border, #d1d5db);
        border-radius: 2px; /* tighter corners */
    }

    .fei-groups-list .fei-group-item {
        display: flex;
        align-items: center;
        gap: 1px; /* minimal space between checkbox and label */
        padding: 0; /* no vertical padding between rows */
        border-radius: 0;
        line-height: 1.5; /* ultra-compact line height */
        margin: 0; /* remove any default extra space */
    }

    .fei-groups-list input.checkbox {
        margin: 0 6px 0 0; /* Nextcloud checkbox with right spacing */
    }

    .fei-groups-list label {
        margin: 0; /* remove any default label margins */
    }

    /* Reduce hover highlight thickness to avoid visual spacing */
    .fei-groups-list .fei-group-item:hover {
        background: var(--color-background-hover, rgba(0, 0, 0, 0.03));
    }

    .fei-groups-list input[type="checkbox"]:focus + label {
        outline: 2px solid var(--color-primary, #1e72ff);
        outline-offset: 2px;
        border-radius: 4px;
    }

    .fei-groups-list label {
        cursor: pointer;
        user-select: none;
        flex: 1;
    }

    .fei-groups-disabled .fei-group-item,
    .fei-groups-disabled input[type="checkbox"],
    .fei-groups-disabled label {
        opacity: 0.6;
        cursor: not-allowed;
    }
</style>

<form action="<?php p($saveUrl); ?>" method="post" class="section">
    <input type="hidden" name="requesttoken"
           value="<?php p(OC::$server->getCsrfTokenManager()->getToken()->getEncryptedValue()); ?>">
    <h2><?php p($l->t('Front-end Insight settings')); ?></h2>
    <div class="settings-help"
         style="margin: 8px 0;"><?php p($l->t('Configure which client-side events this app should collect.')); ?></div>

    <div style="margin: 8px 0;">
        <input type="checkbox" class="checkbox" id="fei-collect-errors" name="collect_errors"
               value="1" <?php if ($collect_errors) {
               	echo 'checked';
               } ?>>
        <label for="fei-collect-errors"><?php p($l->t('Collect page errors (window.onerror)')); ?></label>
    </div>

    <div style="margin: 8px 0;">
        <input type="checkbox" class="checkbox" id="fei-collect-unhandled" name="collect_unhandled_rejections"
               value="1" <?php if ($collect_unhandled_rejections) {
               	echo 'checked';
               } ?>>
        <label for="fei-collect-unhandled"><?php p($l->t('Collect unhandled promise rejections (unhandledrejection)')); ?></label>
    </div>
    <div style="margin: 16px 0;">
        <label id="fei-groups-label" for="fei-groups" class="inlineblock"
               style="margin-right: 8px; font-weight: 600; font-size: 1.1em;"><?php p($l->t('Allowed groups')); ?></label>
    </div>
    <?php $disabled = empty($available_groups) || count($available_groups) <= 1; ?>
    <div class="settings-help"><?php p($l->t('The following groups of usersn will be able to see the reports')); ?></div>
    <div style="margin: 8px 0;" class="<?php echo $disabled ? 'fei-groups-disabled' : ''; ?>">
        <div class="fei-groups-list" role="group" aria-labelledby="fei-groups-label">
            <?php foreach (($available_groups ?? []) as $g):
            	$gid = $g['gid'];
            	$dn = $g['displayName'];
            	$checked = in_array($gid, ($selected_groups ?? []), true);
            	if (!$checked) {
            		$onlyOne = is_array($available_groups ?? null) && count($available_groups) === 1;
            		$isAdminName = (strtolower((string)$gid) === 'admin') || (strtolower((string)$dn) === 'admin');
            		if ($disabled && $onlyOne && $isAdminName) {
            			$checked = true;
            		}
            	}
            	?>
                <div class="fei-group-item">
                    <input type="checkbox" class="checkbox"
                           id="fei-group-<?php p(htmlspecialchars($gid, ENT_QUOTES)); ?>" name="allowed_groups[]"
                           value="<?php p($gid); ?>" <?php echo $checked ? 'checked' : ''; ?> <?php echo $disabled ? 'disabled' : ''; ?> />
                    <label for="fei-group-<?php p(htmlspecialchars($gid, ENT_QUOTES)); ?>"><?php p($dn); ?></label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php if ($disabled) { ?>
        <div>
            <span class="hint"><?php p($l->t('Selection is disabled because only one group is available.')); ?></span>
        </div>
    <?php } ?>
    <?php if (!empty($current_user_groups)) { ?>
        <div class="description" style="margin-top: 6px;">
            <?php p($l->t('Your groups:')); ?>
            <?php p(implode(', ', array_map('strval', $current_user_groups))); ?>
        </div>
    <?php } ?>
    <div style="margin: 8px 0;">
        <label for="fei-retention" class="inlineblock"
               style="margin-right: 8px; font-weight: 600; font-size: 1.1em;"><?php p($l->t('Retention (hours)')); ?></label>
    </div>
    <div style="margin: 8px 0;">
        <input type="number" class="number" id="fei-retention" name="retention_hours" min="1"
               value="<?php p((int)($retention_hours ?? (24 * 30))); ?>" style="width: 120px; margin-right: 16px;">

        <div class="description"><?php p($l->t('Reports older than this period will be permanently deleted by a background job.')); ?></div>
    </div>

    <div style="margin: 16px 0;">
        <button type="submit" id="fei-save-btn"
                class="primary fei-save-btn"><?php p($l->t('Save settings')); ?></button>
        <?php if (!empty($saved)) { ?>
            <span id="fei-saved-msg" class="msg success inlineblock"
                  style="transition: opacity 300ms ease; margin-left: 8px;"><?php p($l->t('Settings saved.')); ?></span>
        <?php } ?>
    </div>
</form>

<script nonce="<?php p(OC::$server->getContentSecurityPolicyNonceManager()->getNonce()); ?>">
    (function () {
        function fadeAndRemove(el) {
            setTimeout(function () {
                el.style.opacity = '0';
                setTimeout(function () {
                    if (el && el.parentNode) {
                        el.parentNode.removeChild(el);
                    }
                }, 350);
            }, 2000);
        }

        function showSavedMessage() {
            var span = document.getElementById('fei-saved-msg');
            if (!span) {
                var btn = document.getElementById('fei-save-btn');
                var container = btn ? btn.parentElement : document.querySelector('form.section');
                if (!container) return;
                span = document.createElement('span');
                span.id = 'fei-saved-msg';
                span.className = 'msg success inlineblock';
                span.style.transition = 'opacity 300ms ease';
                span.style.marginLeft = '8px';
                span.setAttribute('role', 'status');
                span.setAttribute('aria-live', 'polite');
                span.textContent = <?php echo json_encode($l->t('Settings saved.')); ?>;
                container.appendChild(span);
            }
            span.style.opacity = '1';
            fadeAndRemove(span);
        }

        function showErrorMessage(message) {
            var span = document.getElementById('fei-error-msg');
            if (!span) {
                var btn = document.getElementById('fei-save-btn');
                var container = btn ? btn.parentElement : document.querySelector('form.section');
                if (!container) return;
                span = document.createElement('span');
                span.id = 'fei-error-msg';
                span.className = 'msg error inlineblock';
                span.style.transition = 'opacity 300ms ease';
                span.style.marginLeft = '8px';
                span.setAttribute('role', 'status');
                span.setAttribute('aria-live', 'polite');
                container.appendChild(span);
            }
            span.textContent = message || <?php echo json_encode($l->t('Failed to save settings. Please try again.')); ?>;
            span.style.opacity = '1';
            fadeAndRemove(span);
        }

        try {
            // fade existing server-rendered message (ensure visible first)
            var existing = document.getElementById('fei-saved-msg');
            if (existing) {
                existing.style.opacity = '1';
                fadeAndRemove(existing);
            }
            // progressive enhancement: ajax submit
            var form = document.querySelector('form.section');
            if (form && window.fetch) {
                form.addEventListener('submit', function (ev) {
                    ev.preventDefault();
                    var action = form.getAttribute('action');
                    var formData = new FormData(form);
                    var btn = document.getElementById('fei-save-btn');
                    if (btn) btn.setAttribute('aria-busy', 'true');
                    fetch(action, {
                        method: 'POST',
                        headers: {'Accept': 'application/json'},
                        body: formData
                    }).then(function (res) {
                        return res.json();
                    })
                        .then(function (data) {
                            if (btn) btn.removeAttribute('aria-busy');
                            if (data && (data.success === true || data.status === 'success')) {
                                showSavedMessage();
                            } else {
                                // show error message
                                showErrorMessage((data && data.message) ? data.message : undefined);
                            }
                        }).catch(function (e) {
                        if (btn) btn.removeAttribute('aria-busy');
                        // show error message
                        showErrorMessage();
                    });
                });
            }
        } catch (e) {
            // noop
        }
    })();
</script>