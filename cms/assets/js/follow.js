/**
 * Слежение за старшим: синхронная прокрутка текста вечерни/литургии.
 *
 * Блок #cms-follow (правый нижний угол) показывает флаги «Включить слежение»
 * (старшим) и «Следить за старшим» (остальным, когда старший ведёт).
 * Позиция передаётся долей 0..1, но не от всего документа, а от высоты самого
 * текста (.cms-vespers-text): шапка, карточка с датой, блок «Чтецы» (виден
 * только старшему) и подвал в расчёт не входят — иначе у старшего и участника
 * одна и та же доля приходилась бы на разные строки.
 *
 * Ведущий отправляет позицию в конце каждой прокрутки (debounce), поэтому
 * доезжает даже последний сдвиг. Следящие опрашивают состояние каждые 0,5 с
 * и плавно подкручиваются к позиции ведущего.
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

    var POLL_FOLLOW_MS = 500;    // опрос следящего
    var POLL_IDLE_MS = 2000;     // опрос остальных (искать ведущего)
    var POS_DEBOUNCE_MS = 150;   // «прокрутка закончилась» — отправляем позицию
    var ANIM_MS = 400;           // плавный переход к позиции ведущего
    var HEARTBEAT_MS = 10000;    // ведущий продлевает себя, даже если не листает

    var seq = 0;
    var appliedSeq = 0;
    var inFlight = 0;
    var pollTimer = null;
    var posTimer = null;
    var heartbeatTimer = null;
    var animFrame = null;
    var wasFollowing = role === 'follower' && leader !== null;

    function clampRatio(value) {
        return Math.min(1, Math.max(0, value));
    }

    function textBox() {
        return document.querySelector('.cms-vespers-text');
    }

    /**
     * Доля прокрутки самого текста: 0 — верх текста поравнялся с верхом окна,
     * 1 — низ текста с низом окна. Обвязка страницы не участвует.
     */
    function scrollRatio() {
        var doc = document.documentElement;
        var vh = window.innerHeight;
        var scrollY = window.pageYOffset || doc.scrollTop || 0;
        var box = textBox();
        if (box) {
            var top = box.getBoundingClientRect().top + scrollY;
            var max = box.offsetHeight - vh;
            if (max <= 0) {
                return 0; // текст короче окна — прокручивать нечего
            }
            return clampRatio((scrollY - top) / max);
        }
        var maxDoc = doc.scrollHeight - vh;
        if (maxDoc <= 0) {
            return 0;
        }
        return clampRatio(scrollY / maxDoc);
    }

    /** Куда прокрутиться, чтобы текст оказался на доле ratio (null — некуда). */
    function targetY(ratio) {
        var doc = document.documentElement;
        var vh = window.innerHeight;
        var scrollY = window.pageYOffset || doc.scrollTop || 0;
        var box = textBox();
        if (box) {
            var top = box.getBoundingClientRect().top + scrollY;
            var max = box.offsetHeight - vh;
            if (max <= 0) {
                return null;
            }
            return Math.round(top + clampRatio(ratio) * max);
        }
        var maxDoc = doc.scrollHeight - vh;
        if (maxDoc <= 0) {
            return null;
        }
        return Math.round(clampRatio(ratio) * maxDoc);
    }

    function animateToRatio(ratio) {
        var target = targetY(ratio);
        if (target === null) {
            return;
        }
        var doc = document.documentElement;
        var start = window.pageYOffset || doc.scrollTop || 0;
        if (Math.abs(start - target) <= 2) {
            return;
        }
        var startAt = null;
        if (animFrame) {
            cancelAnimationFrame(animFrame);
        }
        function step(now) {
            if (startAt === null) {
                startAt = now;
            }
            var t = Math.min(1, (now - startAt) / ANIM_MS);
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

    function pollDelay() {
        var following = role === 'follower' && followEl && followEl.checked && leader !== null;
        return following ? POLL_FOLLOW_MS : POLL_IDLE_MS;
    }

    function schedulePoll(delay) {
        if (pollTimer) {
            clearTimeout(pollTimer);
        }
        pollTimer = setTimeout(poll, delay);
    }

    function poll() {
        pollTimer = null;
        if (document.hidden) {
            schedulePoll(POLL_IDLE_MS);
            return;
        }
        if (inFlight > 0) {
            schedulePoll(200);
            return;
        }
        post({ poll: 1 });
    }

    /** Ведущий шлёт позицию в конце прокрутки: ловим каждый жест. */
    function sendPositionSoon() {
        if (posTimer) {
            clearTimeout(posTimer);
        }
        posTimer = setTimeout(function () {
            posTimer = null;
            if (role === 'leader') {
                post({ pos: scrollRatio().toFixed(4) });
            }
        }, POS_DEBOUNCE_MS);
    }

    function ensureHeartbeat() {
        if (role === 'leader') {
            if (!heartbeatTimer) {
                heartbeatTimer = setInterval(function () {
                    if (role === 'leader' && !document.hidden) {
                        post({ pos: scrollRatio().toFixed(4) });
                    }
                }, HEARTBEAT_MS);
            }
            return;
        }
        if (heartbeatTimer) {
            clearInterval(heartbeatTimer);
            heartbeatTimer = null;
        }
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

        var following = role === 'follower' && !!leader && followEl && followEl.checked;
        if (wasFollowing && !following && role !== 'leader') {
            // ведущий пропал — снимаем подписку, чтобы она не «ожила» у нового
            post({ follow: 0 });
        }
        wasFollowing = following;

        ensureHeartbeat();

        if (following) {
            animateToRatio(leader.position);
        }

        schedulePoll(pollDelay());
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
        if (role === 'leader') {
            sendPositionSoon();
        }
    }, { passive: true });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            poll();
            if (role === 'leader') {
                post({ pos: scrollRatio().toFixed(4) });
            }
        }
    });

    ensureHeartbeat();
    poll();
})();
