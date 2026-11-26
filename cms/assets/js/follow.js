/**
 * Слежение за старшим: синхронная прокрутка текста вечерни/литургии.
 *
 * Блок #cms-follow (правый нижний угол) показывает флаги «Включить слежение»
 * (старшим) и «Следить за старшим» (остальным, когда старший ведёт).
 * Позиция передаётся долей прокрутки 0..1 — у участников разный кегль и высота
 * окна, поэтому пиксели напрямую не годятся.
 */
(function () {
    'use strict';

    var panel = document.getElementById('cms-follow');
    if (!panel) {
        return;
    }

    var url = panel.getAttribute('data-url');
    var meetingId = panel.getAttribute('data-meeting-id');
    var csrf = panel.getAttribute('data-csrf');
    var canLead = panel.getAttribute('data-can-lead') === '1';
    var leadEl = document.getElementById('cms-follow-lead');
    var followWrap = document.getElementById('cms-follow-follow-wrap');
    var followEl = document.getElementById('cms-follow-follow');
    var statusEl = document.getElementById('cms-follow-status');

    var role = panel.getAttribute('data-role') || '';
    var leader = null;
    try {
        leader = JSON.parse(panel.getAttribute('data-leader') || 'null');
    } catch (e) {
        leader = null;
    }

    var POLL_MS = 1800;   // опрос состояния и сигнал «я на странице»
    var POS_MS = 400;     // не чаще раза в 0,4 с отправляем позицию
    var seq = 0;
    var appliedSeq = 0;
    var inFlight = 0;
    var posTimer = null;
    var animFrame = null;

    function scrollRatio() {
        var doc = document.documentElement;
        var max = doc.scrollHeight - window.innerHeight;
        if (max <= 0) {
            return 0;
        }
        var y = window.pageYOffset || doc.scrollTop || 0;
        return Math.min(1, Math.max(0, y / max));
    }

    function animateToRatio(ratio) {
        var doc = document.documentElement;
        var max = doc.scrollHeight - window.innerHeight;
        if (max <= 0) {
            return;
        }
        var target = Math.round(ratio * max);
        var start = window.pageYOffset || doc.scrollTop || 0;
        if (Math.abs(start - target) <= 2) {
            return;
        }
        var startAt = null;
        var duration = 450;
        if (animFrame) {
            cancelAnimationFrame(animFrame);
        }
        function step(now) {
            if (startAt === null) {
                startAt = now;
            }
            var t = Math.min(1, (now - startAt) / duration);
            window.scrollTo(0, Math.round(start + (target - start) * t));
            if (t < 1) {
                animFrame = requestAnimationFrame(step);
            } else {
                animFrame = null;
            }
        }
        animFrame = requestAnimationFrame(step);
    }

    function post(data) {
        var id = ++seq;
        inFlight++;
        var body = new URLSearchParams();
        body.append('csrf', csrf);
        body.append('meeting_id', meetingId);
        Object.keys(data || {}).forEach(function (key) {
            body.append(key, data[key]);
        });

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        })
            .then(function (response) { return response.json(); })
            .then(function (state) {
                inFlight--;
                if (id < appliedSeq) {
                    return; // ответ на устаревший запрос
                }
                appliedSeq = id;
                if (state && state.ok) {
                    render(state);
                }
            })
            .catch(function () {
                inFlight--;
            });
    }

    function render(state) {
        role = state.role || '';
        leader = state.leader || null;

        var followingAvailable = !!leader && role !== 'leader';
        if (leadEl) {
            leadEl.checked = role === 'leader';
        }
        if (followWrap) {
            followWrap.hidden = !followingAvailable;
        }
        if (followEl) {
            followEl.checked = role === 'follower' && !!leader;
        }
        panel.hidden = !(canLead || followingAvailable);

        if (statusEl) {
            if (role === 'leader') {
                statusEl.textContent = state.followers > 0
                    ? 'Ведёте вы · следят: ' + state.followers
                    : 'Ведёте вы';
            } else if (leader) {
                statusEl.textContent = 'Ведёт: ' + leader.name;
            } else if (canLead) {
                statusEl.textContent = 'Слежение выключено';
            } else {
                statusEl.textContent = '';
            }
        }

        if (role === 'follower' && followEl && followEl.checked && leader) {
            animateToRatio(leader.position);
        }
    }

    function poll() {
        if (document.hidden || inFlight > 0) {
            return;
        }
        post({});
    }

    if (leadEl) {
        leadEl.addEventListener('change', function () {
            post(leadEl.checked
                ? { lead: 1, pos: scrollRatio().toFixed(4) }
                : { lead: 0 });
        });
    }

    if (followEl) {
        followEl.addEventListener('change', function () {
            post({ follow: followEl.checked ? 1 : 0 });
        });
    }

    window.addEventListener('scroll', function () {
        if (role !== 'leader' || posTimer || inFlight > 0) {
            return;
        }
        posTimer = setTimeout(function () {
            posTimer = null;
            if (role === 'leader') {
                post({ pos: scrollRatio().toFixed(4) });
            }
        }, POS_MS);
    }, { passive: true });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            poll();
        }
    });

    setInterval(poll, POLL_MS);
    poll();
})();
