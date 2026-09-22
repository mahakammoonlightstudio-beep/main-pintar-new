/**
 * Main Pintar - admin.js
 * UI helpers (toast, konfirmasi, salin) + host monitor polling live.
 */
(function () {
    'use strict';

    var body = document.body;
    var POLL_HOST = parseInt(body.getAttribute('data-poll-host') || '3000', 10);
    var toastContainer = document.getElementById('toastContainer');

    /* ===== Toast ===== */
    function toast(msg, type) {
        if (!toastContainer) return;
        var t = document.createElement('div');
        t.className = 'toast toast-' + (type || 'info');
        t.setAttribute('role', 'status');
        t.textContent = msg;
        toastContainer.appendChild(t);
        setTimeout(function () { t.remove(); }, 4000);
    }

    /* ===== Konfirmasi aksi destruktif (data-confirm="...") ===== */
    function initConfirm() {
        document.querySelectorAll('[data-confirm]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (!window.confirm(el.getAttribute('data-confirm'))) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        });
    }

    /* ===== Toggle field target broadcast (admin/notifikasi.php) ===== */
    function initToggleTarget() {
        var sel = document.querySelector('[data-toggle-target]');
        if (!sel) return;
        var field = document.getElementById('field-user');
        var sync = function () {
            if (field) field.hidden = sel.value !== 'user';
        };
        sel.addEventListener('change', sync);
        sync();
    }

    /* ===== Salin kode ruangan ===== */
    function initCopy() {
        document.querySelectorAll('[data-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-copy');
                navigator.clipboard.writeText(text).then(function () {
                    toast('Kode disalin: ' + text, 'ok');
                }).catch(function () {
                    toast('Gagal menyalin', 'error');
                });
            });
        });
    }

    /* ===== Tabel cari ===== */
    function initSearch() {
        document.querySelectorAll('[data-table-search]').forEach(function (input) {
            var table = document.getElementById(input.getAttribute('data-table-search'));
            if (!table) return;
            input.addEventListener('input', function () {
                var q = input.value.trim().toLowerCase();
                table.querySelectorAll('tbody tr').forEach(function (row) {
                    row.hidden = row.textContent.toLowerCase().indexOf(q) === -1;
                });
            });
        });
    }

    /* ===== Host monitor: polling api/poll.php ===== */
    function initHostMonitor() {
        var wrap = document.getElementById('hostMonitor');
        if (!wrap) return;

        var sesiId = parseInt(wrap.getAttribute('data-sesi') || '0', 10);
        var paused = document.hidden;
        var lastState = '';
        var inFlight = false;

        function esc(s) {
            var d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        // Halaman admin ada di /admin/, jadi path ke API harus ../api/
        var API_URL = '../api/poll.php';

        function hostAction(action) {
            fetch(API_URL + '?sesi=' + sesiId, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: action })
            }).then(function () { poll(); }).catch(function () {});
        }

        function render(data) {
            var el;

            el = document.getElementById('hostStatus');
            if (el) {
                var map = { menunggu: 'Menunggu', berjalan: 'Berjalan', selesai: 'Selesai' };
                el.textContent = map[data.status] || data.status;
            }

            el = document.getElementById('hostPeserta');
            if (el) {
                if (!data.peserta || data.peserta.length === 0) {
                    el.innerHTML = '<p class="empty">Belum ada peserta bergabung.</p>';
                } else {
                    var rows = data.peserta.map(function (p, i) {
                        return '<li><span class="lb-rank">' + (i + 1) + '</span>' +
                            '<span class="lb-name">' + esc(p.nama_lengkap || p.nama) + '</span>' +
                            '<span class="lb-score">' + (p.total_poin || 0) + '</span></li>';
                    }).join('');
                    el.innerHTML = '<ul class="lb-list">' + rows + '</ul>';
                }
            }

            el = document.getElementById('hostSoal');
            if (el) {
                if (data.status === 'menunggu') {
                    el.innerHTML = '<p class="notice notice-info">Klik "Mulai" untuk memulai soal pertama.</p>';
                } else if (data.soal) {
                    var html = '<p class="q-count">Soal ' + data.nomor + ' dari ' + data.total +
                        ' &middot; dijawab ' + data.jumlah_jawab + '/' + data.peserta.length + '</p>' +
                        '<p class="question-text">' + esc(data.soal.pertanyaan) + '</p>';
                    if (data.semua_jawab || data.status === 'selesai') {
                        html += distHtml(data.distribusi);
                    }
                    el.innerHTML = html;
                } else if (data.status === 'selesai') {
                    el.innerHTML = '<p class="notice notice-ok">Semua soal selesai. Ranking di bawah.</p>';
                }
            }

            el = document.getElementById('hostRanking');
            if (el) {
                if (data.status === 'selesai' && data.ranking && data.ranking.length) {
                    var rows = data.ranking.map(function (p, i) {
                        return '<li><span class="lb-rank">' + (i + 1) + '</span>' +
                            '<span class="lb-name">' + esc(p.nama) + '</span>' +
                            '<span class="lb-score">' + p.total_poin + '</span></li>';
                    }).join('');
                    el.innerHTML = '<ul class="lb-list">' + rows + '</ul>';
                } else {
                    el.innerHTML = '<p class="empty">Ranking tampil setelah kuis selesai.</p>';
                }
            }

            var btnStart = document.getElementById('btnStart');
            var btnNext = document.getElementById('btnNext');
            var btnFinish = document.getElementById('btnFinish');
            if (btnStart) btnStart.hidden = data.status !== 'menunggu';
            if (btnNext) btnNext.hidden = data.status !== 'berjalan';
            if (btnFinish) btnFinish.hidden = data.status !== 'berjalan';
        }

        function distHtml(d) {
            if (!d) return '';
            var keys = ['A', 'B', 'C', 'D'];
            var total = keys.reduce(function (s, k) { return s + (d[k] || 0); }, 0);
            if (!total) return '';
            var html = '<div class="answer-dist mt-16">';
            keys.forEach(function (k) {
                var n = d[k] || 0;
                var pct = total ? Math.round(n / total * 100) : 0;
                html += '<div class="dist-col"><span class="dist-count">' + n + '</span>' +
                    '<div class="dist-bar dist-' + k.toLowerCase() + '" style="height:' + Math.max(pct, 2) + '%"></div>' +
                    '<span class="dist-label dist-' + k.toLowerCase() + '">' + k + ' &middot; ' + pct + '%</span></div>';
            });
            html += '</div>';
            return html;
        }

        function poll() {
            if (paused || inFlight) return; /* cegah polling menumpuk */
            inFlight = true;
            fetch(API_URL + '?sesi=' + sesiId, { cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.error) return;
                    var state = JSON.stringify([data.status, data.nomor, data.jumlah_jawab, data.peserta]);
                    if (state !== lastState) {
                        lastState = state;
                        render(data);
                    }
                })
                .catch(function () {})
                .finally(function () { inFlight = false; });
        }

        var btnStart = document.getElementById('btnStart');
        var btnNext = document.getElementById('btnNext');
        var btnFinish = document.getElementById('btnFinish');
        if (btnStart) btnStart.addEventListener('click', function () { hostAction('start'); });
        if (btnNext) btnNext.addEventListener('click', function () { hostAction('next'); });
        if (btnFinish) btnFinish.addEventListener('click', function () {
            if (confirm('Selesai & simpan skor semua peserta?')) hostAction('finish');
        });

        document.addEventListener('visibilitychange', function () {
            paused = document.hidden;
            if (!paused) poll();
        });

        poll();
        setInterval(poll, POLL_HOST);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initConfirm();
        initToggleTarget();
        initCopy();
        initSearch();
        initHostMonitor();
    });
})();
