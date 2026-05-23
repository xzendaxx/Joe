@extends('tablar::master')

@inject('layoutHelper', 'TakiElias\Tablar\Helpers\LayoutHelper')

@section('tablar_css')
    @stack('css')
    @yield('css')
@stop

@section('classes_body', $layoutHelper->makeBodyClasses())

@includeIf('tablar::layouts.'. config('tablar.layout'))

@section('tablar_js')
    @auth
        <script>
            window.addEventListener('pageshow', function (event) {
                if (event.persisted) {
                    window.location.reload();
                }
            });

            (function () {
                const logoutUrl = @json(route('logout'));
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                let isInternalNavigation = false;
                let logoutSent = false;

                function markInternalNavigation() {
                    isInternalNavigation = true;
                }

                document.addEventListener('click', function (event) {
                    const link = event.target.closest('a[href]');

                    if (!link || link.target === '_blank' || link.hasAttribute('download')) {
                        return;
                    }

                    const href = link.getAttribute('href') || '';

                    if (href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
                        return;
                    }

                    try {
                        const targetUrl = new URL(link.href, window.location.origin);

                        if (targetUrl.origin === window.location.origin) {
                            markInternalNavigation();
                        }
                    } catch (error) {
                        // Ignore malformed links and keep the current guard state.
                    }
                }, true);

                document.addEventListener('submit', markInternalNavigation, true);

                window.addEventListener('keydown', function (event) {
                    if (event.key === 'F5' || ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'r')) {
                        markInternalNavigation();
                    }
                });

                function sendLogoutBeacon() {
                    if (logoutSent || isInternalNavigation || !csrfToken) {
                        return;
                    }

                    logoutSent = true;

                    const payload = new FormData();
                    payload.append('_token', csrfToken);
                    payload.append('close_intent', '1');

                    if (navigator.sendBeacon) {
                        navigator.sendBeacon(logoutUrl, payload);
                        return;
                    }

                    fetch(logoutUrl, {
                        method: 'POST',
                        body: payload,
                        credentials: 'same-origin',
                        keepalive: true,
                    }).catch(function () {
                        // Ignore shutdown-time network errors.
                    });
                }

                window.addEventListener('pagehide', sendLogoutBeacon);
                window.addEventListener('beforeunload', sendLogoutBeacon);
            })();
        </script>
    @endauth
    @stack('js')
    @yield('js')
@stop
