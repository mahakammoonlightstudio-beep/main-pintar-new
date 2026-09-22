/**
 * Main Pintar - quiz.js
 * Tema, timer solo, polling live (peserta). Host monitor pakai admin.js.
 * Scroll reveal, scroll-to-top, smooth transitions.
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var body = document.body;
    var themeBtn = document.querySelector('[data-theme-toggle]');
    var POLL_PESERTA = parseInt(body.getAttribute('data-poll-peserta') || '4000', 10);

    /* ===== Scroll Reveal (IntersectionObserver) ===== */
    function initScrollReveal() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.querySelectorAll('.reveal').forEach(function (el) {
                el.classList.add('revealed');
            });
            return;
        }
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -10% 0px', threshold: 0.1 });

        document.querySelectorAll('.reveal').forEach(function (el) {
            observer.observe(el);
        });
    }

    /* ===== Scroll to Top Button ===== */
    function initScrollTop() {
        var btn = document.querySelector('.scroll-top-btn');
        if (!btn) return;

        var onScroll = function () {
            if (window.scrollY > 300) {
                btn.hidden = false;
                requestAnimationFrame(function () { btn.classList.add('visible'); });
            } else {
                btn.classList.remove('visible');
                setTimeout(function () { btn.hidden = true; }, 300);
            }
        };
        window.addEventListener('scroll', onScroll, { passive: true });

        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    /* ===== Page Enter Animation ===== */
    function initPageEnter() {
        var main = document.getElementById('main');
        if (!main) return;
        main.classList.add('page-enter');
        requestAnimationFrame(function () {
            main.classList.add('page-enter-active');
        });
    }

    /* ===== Theme Toggle (dengan animasi transisi, bukan instan) =====
       Mode 'system' (dari halaman Pengaturan): mengikuti prefers-color-scheme
       dan ikut berubah bila OS pengguna berganti tema. */
    var LABELS = document.documentElement.lang === 'en'
        ? { dark: 'Light mode', light: 'Dark mode' }
        : { dark: 'Mode terang', light: 'Mode gelap' };

    function applyTheme(dark) {
        root.dataset.theme = dark ? 'dark' : 'light';
        try { localStorage.setItem('mp_theme', root.dataset.theme); } catch (e) {}
        if (themeBtn) {
            themeBtn.textContent = dark ? LABELS.dark : LABELS.light;
            themeBtn.setAttribute('aria-pressed', dark ? 'true' : 'false');
        }
    }

    function systemPrefersDark() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function applyStoredPreference() {
        var stored = null;
        try { stored = localStorage.getItem('mp_theme'); } catch (e) {}
        if (stored === 'system' || stored === null) {
            applyTheme(systemPrefersDark());
        } else {
            applyTheme(stored === 'dark');
        }
    }

    /* Ikuti perubahan tema OS saat mode 'system' aktif */
    var mediaTheme = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
    if (mediaTheme) {
        var onSystemChange = function () {
            var stored = null;
            try { stored = localStorage.getItem('mp_theme'); } catch (e) {}
            if (stored === 'system' || stored === null) {
                root.dataset.theme = systemPrefersDark() ? 'dark' : 'light';
            }
        };
        if (mediaTheme.addEventListener) {
            mediaTheme.addEventListener('change', onSystemChange);
        } else if (mediaTheme.addListener) {
            mediaTheme.addListener(onSystemChange);
        }
    }

    function toggleTheme(e) {
        /* Jika preferensi tersimpan 'system', langkah pertama memilih tema
           eksplisit sesuai kondisi terang/gelap yang sedang terlihat. */
        var current = root.dataset.theme === 'dark' || (root.dataset.theme !== 'light' && systemPrefersDark());
        var dark = !current;
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        /* Titik asal animasi: tombol tema (fallback: tengah layar) */
        var ox = window.innerWidth / 2, oy = window.innerHeight / 2;
        if (e && e.currentTarget && e.currentTarget.getBoundingClientRect) {
            var r = e.currentTarget.getBoundingClientRect();
            ox = r.left + r.width / 2;
            oy = r.top + r.height / 2;
        }

        if (typeof document.startViewTransition === 'function' && !reduce) {
            /* Browser modern: reveal melingkar dari tombol tema */
            var vt = document.startViewTransition(function () { applyTheme(dark); });
            vt.ready.then(function () {
                var radius = Math.hypot(
                    Math.max(ox, window.innerWidth - ox),
                    Math.max(oy, window.innerHeight - oy)
                );
                document.documentElement.animate(
                    {
                        clipPath: [
                            'circle(0px at ' + ox + 'px ' + oy + 'px)',
                            'circle(' + radius + 'px at ' + ox + 'px ' + oy + 'px)'
                        ]
                    },
                    { duration: 550, easing: 'ease-in-out', pseudoElement: '::view-transition-new(root)' }
                );
            }).catch(function () { /* animasi gagal: tema tetap berganti */ });
        } else {
            /* Fallback: transisi warna global lewat class sementara */
            root.classList.add('theme-transitioning');
            applyTheme(dark);
            window.setTimeout(function () { root.classList.remove('theme-transitioning'); }, 500);
        }
    }

    function initTheme() {
        if (!themeBtn) return;
        applyStoredPreference();
        var dark = root.dataset.theme === 'dark';
        themeBtn.textContent = dark ? 'Mode terang' : 'Mode gelap';
        themeBtn.setAttribute('aria-pressed', dark ? 'true' : 'false');
        themeBtn.addEventListener('click', toggleTheme);
    }

    /* ===== Confetti & angka count-up ===== */
    function fireConfetti(count) {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        var n = count || 90;
        var colors = ['#46178f', '#7b48e0', '#d89e00', '#e8b84d', '#1368ce', '#e21b3c'];
        var container = document.createElement('div');
        container.className = 'confetti-container';
        container.setAttribute('aria-hidden', 'true');
        document.body.appendChild(container);
        for (var i = 0; i < n; i++) {
            var p = document.createElement('span');
            p.className = 'confetti' + (Math.random() < 0.35 ? ' round' : '');
            p.style.left = (Math.random() * 100) + 'vw';
            p.style.backgroundColor = colors[i % colors.length];
            p.style.animationDuration = (2.2 + Math.random() * 1.8) + 's';
            p.style.animationDelay = (Math.random() * 0.7) + 's';
            var s = 7 + Math.random() * 7;
            p.style.width = s + 'px';
            p.style.height = (s * (Math.random() < 0.5 ? 1 : 1.4)) + 'px';
            container.appendChild(p);
        }
        window.setTimeout(function () { container.remove(); }, 5200);
    }

    function initResultEffects() {
        /* Angka naik mulus untuk elemen <span data-countup="..."> */
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        document.querySelectorAll('[data-countup]').forEach(function (el) {
            var target = parseInt(el.getAttribute('data-countup'), 10) || 0;
            var suffix = el.getAttribute('data-suffix') || '';
            if (reduce) { el.textContent = target + suffix; return; }
            var dur = 1100;
            var t0 = performance.now();
            var frame = function (t) {
                var pr = Math.min(1, (t - t0) / dur);
                var eased = 1 - Math.pow(1 - pr, 3);
                el.textContent = Math.round(target * eased) + suffix;
                if (pr < 1) requestAnimationFrame(frame);
            };
            requestAnimationFrame(frame);
        });
        /* Perayaan: halaman dengan data-confetti melempar confetti sekali */
        if (document.querySelector('[data-confetti]')) fireConfetti();
    }

    /* ===== Solo: timer + submit jawaban ===== */
    function initSoloQuiz() {
        var form = document.getElementById('quizForm');
        if (!form) return;

        var timerBox = document.getElementById('timerBox');
        var timerBar = document.getElementById('timerBar');
        var sisaInput = form.querySelector('[name="sisa_detik"]');
        var total = parseInt(form.getAttribute('data-waktu') || '20', 10);
        var sisa = total;
        var submitted = false;
        var start = Date.now();

        var warnAt = Math.max(5, Math.ceil(total * 0.1));

        function tick() {
            sisa = Math.max(0, total - Math.floor((Date.now() - start) / 1000));
            if (timerBox) {
                timerBox.textContent = sisa;
                timerBox.classList.toggle('warn', sisa <= warnAt);
            }
            if (timerBar) {
                timerBar.style.width = (sisa / total * 100) + '%';
            }
            if (sisa <= 0 && !submitted) {
                submit(0);
                return;
            }
            if (sisa > 0) setTimeout(tick, 250);
        }

        function submit(sisaDetik) {
            if (submitted) return;
            submitted = true;
            if (sisaInput) sisaInput.value = sisaDetik;
            /* requestSubmit tetap menjalankan validasi & handler submit;
               fallback form.submit() untuk browser lama. */
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }

        form.querySelectorAll('.answer').forEach(function (btn) {
            btn.addEventListener('click', function () {
                form.querySelector('[name="pilihan"]').value = btn.getAttribute('data-pilihan');
                submit(sisa);
            });
        });

        tick();
    }

    /* ===== Solo: overlay feedback + lanjut otomatis ===== */
    function initFeedback() {
        var fb = document.getElementById('feedback');
        if (!fb) return;
        requestAnimationFrame(function () { fb.classList.add('show'); });

        var nextUrl = fb.getAttribute('data-next');
        if (nextUrl) setTimeout(function () { window.location.href = nextUrl; }, 2600);
    }

    /* ===== Live (peserta): polling + jawab ===== */
    function initLivePeserta() {
        var wrap = document.getElementById('liveQuiz');
        if (!wrap) return;

        var sesiId = parseInt(wrap.getAttribute('data-sesi') || '0', 10);
        var timer = null;
        var paused = document.hidden;
        var liveTimerInt = null;
        var sisaLive = 0;
        var inFlight = false;
        var lastStatus = '';

        function esc(s) {
            var d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        // Countdown lokal tiap detik (server tetap sumber kebenaran tiap polling)
        function startLocalCountdown() {
            if (liveTimerInt) clearInterval(liveTimerInt);
            liveTimerInt = setInterval(function () {
                sisaLive = Math.max(0, sisaLive - 1);
                var el = document.getElementById('liveTimer');
                if (el) el.textContent = sisaLive + 's';
            }, 1000);
        }

        function stopLocalCountdown() {
            if (liveTimerInt) { clearInterval(liveTimerInt); liveTimerInt = null; }
        }

        function render(data) {
            var html = '';
            if (data.status === 'menunggu') {
                html = '<div class="question-card text-center"><h2>Menunggu host memulai&hellip;</h2>' +
                    '<p class="mt-8">Kode ruangan: <strong>' + esc(data.kode) + '</strong></p></div>';
            } else if (data.status === 'berjalan' && data.soal) {
                var myAns = data.saya;
                var disabled = !!myAns;
                var opts = ['A', 'B', 'C', 'D'];
                var shapes = { A: 'shape-triangle', B: 'shape-diamond', C: 'shape-circle', D: 'shape-square' };
                sisaLive = Math.max(0, parseInt(data.sisa_detik, 10) || 0);
                /* Tanpa class .reveal: konten hasil polling dibuat setelah
                   IntersectionObserver berjalan, jadi tidak akan pernah
                   ter-observe dan berisiko tampil opacity:0. */
                html = '<div class="question-card">' +
                    '<p class="q-count">Soal ' + data.nomor + ' dari ' + data.total +
                    ' &middot; sisa <strong id="liveTimer">' + sisaLive + 's</strong></p>' +
                    '<p class="question-text">' + esc(data.soal.pertanyaan) + '</p>' +
                    '<div class="answer-options">';
                opts.forEach(function (k) {
                    html += '<button type="button" class="answer answer-' + k.toLowerCase() + '"' +
                        (disabled ? ' disabled' : ' data-pilihan="' + k + '"') + '>' +
                        '<span class="shape"><span class="' + shapes[k] + '"></span></span>' +
                        '<span>' + esc(data.soal['pilihan_' + k.toLowerCase()]) + '</span></button>';
                });
                html += '</div>';
                if (myAns) {
                    html += '<div class="explain mt-16"><strong>' +
                        (myAns.benar ? 'Jawaban benar! +' + myAns.poin_didapat + ' poin' : 'Jawaban salah') +
                        '</strong>' + esc(myAns.penjelasan || '') +
                        '<p class="mt-8">Menunggu soal berikutnya&hellip;</p></div>';
                }
                html += '</div>';
            } else if (data.status === 'selesai') {
                stopLocalCountdown();
                if (lastStatus && lastStatus !== 'selesai') {
                    fireConfetti(120); /* perayaan saat kuis baru saja selesai */
                }
                html = '<div class="question-card text-center"><h2>Kuis selesai!</h2></div>';
                if (data.ranking && data.ranking.length) {
                    html += '<div class="panel mt-16"><header><h2>Peringkat Akhir</h2></header>' +
                        '<div class="panel-body"><ul class="lb-list">';
                    data.ranking.forEach(function (p, i) {
                        html += '<li class="' + (i === 0 ? 'lb-top1' : '') + '">' +
                            '<span class="lb-rank">' + (i === 0 ? '&#127942;' : (i + 1)) + '</span>' +
                            '<span class="lb-name">' + esc(p.nama) + '</span>' +
                            '<span class="lb-score">' + p.total_poin + ' poin</span></li>';
                    });
                    html += '</ul></div></div>';
                }
                html += '<div class="text-center mt-16">' +
                    '<a class="btn btn-primary" href="leaderboard.php">Lihat Peringkat</a> ' +
                    '<a class="btn btn-quiet" href="index.php">Beranda</a></div>';
            }
            wrap.innerHTML = html;
            lastStatus = data.status;

            if (data.status === 'berjalan' && data.soal) {
                startLocalCountdown();
            }

            wrap.querySelectorAll('.answer[data-pilihan]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    jawab(btn.getAttribute('data-pilihan'), btn);
                });
            });
        }

        function jawab(pilihan, btn) {
            btn.disabled = true;
            fetch('api/poll.php?sesi=' + sesiId, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'answer', pilihan: pilihan })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) { poll(); })
                .catch(function () { btn.disabled = false; });
        }

        function poll() {
            if (paused || inFlight) return; /* cegah polling menumpuk */
            inFlight = true;
            fetch('api/poll.php?sesi=' + sesiId, { cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.error) {
                        stopLocalCountdown();
                        wrap.innerHTML = '<p class="notice notice-error">' + esc(data.error) + '</p>';
                        if (timer) { clearInterval(timer); timer = null; }
                        return;
                    }
                    render(data);
                })
                .catch(function () {})
                .finally(function () { inFlight = false; });
        }

        document.addEventListener('visibilitychange', function () {
            paused = document.hidden;
            if (!paused) poll();
        });

        poll();
        timer = setInterval(poll, POLL_PESERTA);
    }

    /* ===== 3D Card Tilt on Mousemove ===== */
    function initCardTilt() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        /* Tilt hanya untuk pointer presisi (mouse); sentuhan pakai hover CSS */
        if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
        var cards = document.querySelectorAll('.kuis-card');
        cards.forEach(function (card) {
            card.addEventListener('mousemove', function (e) {
                var rect = card.getBoundingClientRect();
                var x = e.clientX - rect.left;
                var y = e.clientY - rect.top;
                var centerX = rect.width / 2;
                var centerY = rect.height / 2;
                var rotateX = ((y - centerY) / centerY) * -5;
                var rotateY = ((x - centerX) / centerX) * 5;
                card.style.transform = 'translateY(-6px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg)';
            });
            card.addEventListener('mouseleave', function () {
                card.style.transform = '';
            });
        });
    }

    /* ===== Button Ripple Effect ===== */
    function initButtonRipple() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn, .btn-sm');
            if (!btn) return;
            var rect = btn.getBoundingClientRect();
            var ripple = document.createElement('span');
            var size = Math.max(rect.width, rect.height);
            ripple.style.cssText = 'position:absolute; width:' + size + 'px; height:' + size + 'px; ' +
                'left:' + (e.clientX - rect.left - size / 2) + 'px; ' +
                'top:' + (e.clientY - rect.top - size / 2) + 'px; ' +
                'background:rgba(255,255,255,.3); border-radius:50%; ' +
                'transform:scale(0); animation:ripple .4s ease-out; pointer-events:none;';
            btn.appendChild(ripple);
            setTimeout(function () { ripple.remove(); }, 400);
        });
    }

    /* ===== Add Reveal Classes to Elements ===== */
    function addRevealClasses() {
        var selectors = [
            '.kuis-card', '.panel', '.question-card', '.hero',
            '.result-card', '.metric', '.join-form',
            '.lb-list > li', '.footer-col'
        ];
        selectors.forEach(function (sel) {
            document.querySelectorAll(sel).forEach(function (el, i) {
                if (!el.classList.contains('reveal')) {
                    el.classList.add('reveal');
                    el.style.transitionDelay = (i * 50) + 'ms';
                }
            });
        });
    }

    /* ===== Toggle tampil/sembunyi password ===== */
    function initPasswordToggles() {
        document.querySelectorAll('.password-toggle[data-toggle-for]').forEach(function (btn) {
            var input = document.getElementById(btn.getAttribute('data-toggle-for'));
            if (!input) return;
            btn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
                btn.classList.toggle('is-on', show);
            });
        });
    }

    /* ===== Splash sinematik: sekali per sesi browser ===== */
    function initSplash() {
        var splash = document.getElementById('splash');
        if (!splash) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            splash.remove();
            return;
        }
        var KEY = 'mp_splash';
        var shown = false;
        try {
            shown = sessionStorage.getItem(KEY) === '1';
        } catch (e) {}
        if (shown) { splash.remove(); return; }
        try { sessionStorage.setItem(KEY, '1'); } catch (e) {}

        var MIN = 1400, t0 = Date.now();
        var finish = function () {
            var wait = Math.max(0, MIN - (Date.now() - t0));
            window.setTimeout(function () {
                splash.classList.add('is-done');
                document.body.classList.add('splash-done');
                window.setTimeout(function () { splash.remove(); }, 700);
            }, wait);
        };

        if (document.readyState === 'complete') {
            finish();
        } else {
            window.addEventListener('load', finish);
            /* Jaring pengaman: jangan biarkan splash menutup layar selamanya */
            window.setTimeout(finish, 3500);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSplash();
        initTheme();
        initPageEnter();
        /* addRevealClasses harus sebelum initScrollReveal: elemen yang baru
           diberi class .reveal setelah observer dibuat tidak akan pernah
           ter-observe dan tetap opacity:0 (tidak terlihat). */
        addRevealClasses();
        initScrollReveal();
        initScrollTop();
        initCardTilt();
        initButtonRipple();
        initSoloQuiz();
        initFeedback();
        initLivePeserta();
        initResultEffects();
        initPasswordToggles();
    });

    /* Inject ripple keyframes */
    var style = document.createElement('style');
    style.textContent = '@keyframes ripple { to { transform:scale(2); opacity:0; } }';
    document.head.appendChild(style);
})();