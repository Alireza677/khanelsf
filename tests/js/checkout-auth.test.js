import test from 'node:test';
import assert from 'node:assert/strict';
import { checkoutDestination, sendClientAuth } from '../../resources/js/components/checkout-auth.js';

const checkout = 'https://king.test/checkout';
const response = (status, json, headers = {}) => ({
    ok: status >= 200 && status < 300, status,
    headers: { get: (name) => ({ 'content-type': 'application/json', ...headers })[name] ?? null },
    json: async () => json,
});

test('only the canonical checkout on this origin can be used for authentication continuation', () => {
    assert.equal(checkoutDestination('/checkout', checkout), checkout);
    assert.equal(checkoutDestination(checkout, checkout), checkout);
    for (const unsafe of ['https://evil.test/checkout', '//evil.test/checkout', '/account', '/checkout?next=evil',
        '/checkout#submit', 'javascript:alert(1)', '/\\evil.test/checkout', ' https://king.test/checkout', null]) {
        assert.equal(checkoutDestination(unsafe, checkout), null);
    }
});

test('modal sends the existing CSRF-protected form with same-origin cookies and rejects redirects', async () => {
    const body = new FormData();
    body.set('_token', 'csrf-token');
    body.set('continue', 'checkout');
    const result = await sendClientAuth('/login', body, undefined, async (url, options) => {
        assert.equal(url, '/login');
        assert.equal(options.method, 'POST');
        assert.equal(options.credentials, 'same-origin');
        assert.equal(options.redirect, 'error');
        assert.equal(options.body.get('_token'), 'csrf-token');
        assert.equal(options.headers.Accept, 'application/json');
        return response(200, { authenticated: true, redirect_url: '/checkout' });
    });
    assert.deepEqual(result, { kind: 'success', destination: '/checkout' });
});

test('an HTML page, a redirect or an unrelated JSON 200 never means login success', async () => {
    for (const fake of [response(200, {}, { 'content-type': 'text/html' }), response(302, {}), response(200, { success: true })]) {
        assert.equal((await sendClientAuth('/login', new FormData(), undefined, async () => fake)).kind, 'error');
    }
});

test('validation errors, CSRF expiry and rate limits have distinct outcomes without retrying credentials', async () => {
    const errors = { mobile: ['شماره موبایل یا رمز عبور صحیح نیست.'] };
    assert.deepEqual(await sendClientAuth('/login', new FormData(), undefined, async () => response(422, { errors })), { kind: 'validation', errors });
    assert.equal((await sendClientAuth('/login', new FormData(), undefined, async () => response(419, {}))).kind, 'expired');
    const limited = await sendClientAuth('/login', new FormData(), undefined, async () => response(429, {}, { 'retry-after': '12' }));
    assert.equal(limited.kind, 'limited');
    assert.equal(limited.retryAfter, 12);
});

test('abort is propagated so closed modal requests cannot change a later interaction', async () => {
    const controller = new AbortController();
    controller.abort();
    await assert.rejects(sendClientAuth('/login', new FormData(), controller.signal, async (url, options) => {
        assert.equal(options.signal, controller.signal);
        throw new DOMException('Aborted', 'AbortError');
    }), { name: 'AbortError' });
});
