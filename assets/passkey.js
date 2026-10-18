// assets/passkey.js - passkeys (WebAuthn): login dhe regjistrim. Pa JS inline (CSP).
(function () {
    'use strict';
    if (!window.PublicKeyCredential || !navigator.credentials) return; // shfletues pa mbeshtetje: butonat mbeten te fshehur

    function b64uToBuf(s) {
        s = s.replace(/-/g, '+').replace(/_/g, '/');
        while (s.length % 4) s += '=';
        var bin = atob(s), u = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) u[i] = bin.charCodeAt(i);
        return u.buffer;
    }
    function bufToB64u(buf) {
        var u = new Uint8Array(buf), s = '';
        for (var i = 0; i < u.length; i++) s += String.fromCharCode(u[i]);
        return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }
    function api(box, payload) {
        return fetch(box.dataset.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': box.dataset.csrf },
            body: JSON.stringify(payload)
        }).then(function (r) { return r.json().catch(function () { return { ok: false }; }); });
    }
    function show(box, msg) {
        var el = box.querySelector('.passkey-msg');
        if (!el) return;
        el.textContent = msg || '';
        el.hidden = !msg;
    }
    function errText(box, e) {
        return (e && e.name === 'NotAllowedError') ? box.dataset.msgCancel : box.dataset.msgFail;
    }
    function fixIds(list) {
        return (list || []).map(function (c) { c.id = b64uToBuf(c.id); return c; });
    }

    // ---------- Login ----------
    var loginBox = document.getElementById('passkeyLogin');
    if (loginBox) {
        loginBox.hidden = false;
        var lbtn = document.getElementById('passkeyLoginBtn');
        lbtn.addEventListener('click', function () {
            show(loginBox, '');
            lbtn.disabled = true;
            api(loginBox, { action: 'login_options' }).then(function (res) {
                if (!res.ok) throw { server: res.error };
                var pk = res.options.publicKey;
                pk.challenge = b64uToBuf(pk.challenge);
                if (pk.allowCredentials) pk.allowCredentials = fixIds(pk.allowCredentials);
                return navigator.credentials.get({ publicKey: pk });
            }).then(function (cred) {
                var r = cred.response;
                return api(loginBox, {
                    action: 'login_verify',
                    id: bufToB64u(cred.rawId),
                    clientDataJSON: bufToB64u(r.clientDataJSON),
                    authenticatorData: bufToB64u(r.authenticatorData),
                    signature: bufToB64u(r.signature),
                    userHandle: r.userHandle ? bufToB64u(r.userHandle) : ''
                });
            }).then(function (res) {
                if (res.ok) { window.location.href = res.redirect || 'index.php'; return; }
                throw { server: res.error };
            }).catch(function (e) {
                show(loginBox, (e && e.server) ? e.server : errText(loginBox, e));
                lbtn.disabled = false;
            });
        });
    }

    // ---------- Regjistrim (Settings) ----------
    var regBox = document.getElementById('passkeyRegister');
    if (regBox) {
        regBox.hidden = false;
        var unsupported = document.getElementById('passkeyUnsupported');
        if (unsupported) unsupported.hidden = true;
        var rbtn = document.getElementById('passkeyAddBtn');
        rbtn.addEventListener('click', function () {
            show(regBox, '');
            var pwEl = document.getElementById('passkeyPw');
            var nameEl = document.getElementById('passkeyName');
            rbtn.disabled = true;
            api(regBox, {
                action: 'register_options',
                password: pwEl ? pwEl.value : '',
                name: nameEl ? nameEl.value : ''
            }).then(function (res) {
                if (!res.ok) throw { server: res.error };
                var pk = res.options.publicKey;
                pk.challenge = b64uToBuf(pk.challenge);
                pk.user.id = b64uToBuf(pk.user.id);
                pk.excludeCredentials = fixIds(pk.excludeCredentials);
                return navigator.credentials.create({ publicKey: pk });
            }).then(function (cred) {
                var r = cred.response;
                return api(regBox, {
                    action: 'register_verify',
                    clientDataJSON: bufToB64u(r.clientDataJSON),
                    attestationObject: bufToB64u(r.attestationObject),
                    transports: r.getTransports ? r.getTransports() : []
                });
            }).then(function (res) {
                if (res.ok) { window.location.href = 'settings.php#passkeys'; window.location.reload(); return; }
                throw { server: res.error };
            }).catch(function (e) {
                show(regBox, (e && e.server) ? e.server : errText(regBox, e));
                rbtn.disabled = false;
            });
        });
    }
})();
