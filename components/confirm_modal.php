<?php
// Reusable confirmation modal (<dialog> HTML5 native) untuk aksi destruktif / penting (DRY)
// Cara pakai di form mana pun:
//   data-confirm-title="Judul Modal"
//   data-confirm-message="Isi pesan konfirmasi"
//   data-confirm-btn="Teks Tombol Konfirmasi"
//   data-confirm-variant="danger|warning|default"
?>
<style>
    #loote-confirm-modal::backdrop {
        background: rgba(39, 39, 39, 0.55);
        backdrop-filter: blur(2px);
    }

    #loote-confirm-modal[open] {
        animation: looteModalIn 180ms cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes looteModalIn {
        from {
            opacity: 0;
            transform: translateY(8px) scale(0.98);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        #loote-confirm-modal[open] {
            animation: none;
        }
    }
</style>

<dialog id="loote-confirm-modal"
    aria-labelledby="loote-confirm-title"
    aria-describedby="loote-confirm-message"
    class="p-0 bg-transparent max-w-md w-[calc(100%-2rem)] m-auto overflow-visible">
    <div class="bg-white border border-gray-200/90 shadow-xl p-5 sm:p-6 text-left">
        <!-- Header Modal -->
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-3.5 min-w-0">
                <div id="loote-confirm-icon-wrap"
                    class="w-10 h-10 rounded-full bg-red-50 text-red-600 flex items-center justify-center shrink-0">
                    <i id="loote-confirm-icon" class="ph ph-warning-circle text-xl" aria-hidden="true"></i>
                </div>
                <div class="min-w-0 pt-0.5">
                    <h2 id="loote-confirm-title" class="text-[17px] font-extrabold text-[#111827] tracking-tight leading-snug">
                        Konfirmasi Aksi
                    </h2>
                    <p id="loote-confirm-message" class="mt-1.5 text-sm font-medium text-[#374151] leading-relaxed">
                        Apakah kamu yakin ingin melanjutkan tindakan ini?
                    </p>
                </div>
            </div>

            <button type="button" id="loote-confirm-close"
                aria-label="Tutup dialog konfirmasi"
                class="w-8 h-8 -mr-1.5 -mt-1.5 flex items-center justify-center text-gray-500 hover:text-[#111827] hover:bg-gray-100 rounded-[2px] transition cursor-pointer shrink-0">
                <i class="ph-bold ph-x text-base" aria-hidden="true"></i>
            </button>
        </div>

        <!-- Footer Tombol Aksi -->
        <div class="mt-6 pt-4 border-t border-gray-200 flex items-center justify-end gap-2.5">
            <button type="button" id="loote-confirm-cancel"
                class="px-4 py-2 text-sm font-bold text-[#272727] bg-white hover:bg-gray-100 border border-gray-300 rounded-[2px] transition cursor-pointer">
                Batal
            </button>
            <button type="button" id="loote-confirm-submit"
                class="px-5 py-2 text-sm font-bold text-white bg-red-600 hover:bg-red-700 active:scale-[0.99] rounded-[2px] transition cursor-pointer">
                Ya, Lanjutkan
            </button>
        </div>
    </div>
</dialog>

<script>
    (function () {
        const dialog = document.getElementById('loote-confirm-modal');
        if (!dialog || dialog.dataset.bound === '1') return;
        dialog.dataset.bound = '1';

        const titleEl = document.getElementById('loote-confirm-title');
        const msgEl = document.getElementById('loote-confirm-message');
        const iconWrapEl = document.getElementById('loote-confirm-icon-wrap');
        const iconEl = document.getElementById('loote-confirm-icon');
        const submitBtn = document.getElementById('loote-confirm-submit');
        const cancelBtn = document.getElementById('loote-confirm-cancel');
        const closeBtn = document.getElementById('loote-confirm-close');

        const variants = {
            danger: {
                icon: 'ph-bold ph-trash text-xl',
                iconWrap: 'w-10 h-10 rounded-full bg-red-50 text-red-600 flex items-center justify-center shrink-0',
                btn: 'px-5 py-2 text-sm font-bold text-white bg-red-600 hover:bg-red-700 active:scale-[0.99] rounded-[2px] transition cursor-pointer'
            },
            warning: {
                icon: 'ph-bold ph-warning-circle text-xl',
                iconWrap: 'w-10 h-10 rounded-full bg-[#FFF8DB] text-[#926B00] flex items-center justify-center shrink-0',
                btn: 'px-5 py-2 text-sm font-bold text-[#111827] bg-brand-yellow hover:brightness-95 active:scale-[0.99] rounded-[2px] transition cursor-pointer'
            },
            default: {
                icon: 'ph-bold ph-question text-xl',
                iconWrap: 'w-10 h-10 rounded-full bg-gray-100 text-[#111827] flex items-center justify-center shrink-0',
                btn: 'px-5 py-2 text-sm font-bold text-white bg-[#272727] hover:bg-black active:scale-[0.99] rounded-[2px] transition cursor-pointer'
            }
        };

        const defaultCancelBtnClass = 'px-4 py-2 text-sm font-bold text-[#272727] bg-white hover:bg-gray-100 border border-gray-300 rounded-[2px] transition cursor-pointer';

        let pendingCallback = null;
        let pendingCancelCallback = null;

        function closeModal() {
            pendingCallback = null;
            pendingCancelCallback = null;
            if (dialog.open) dialog.close();
        }

        function openConfirm(options) {
            const v = variants[options.variant] || variants.default;
            titleEl.textContent = options.title || 'Konfirmasi Aksi';
            msgEl.textContent = options.message || 'Apakah kamu yakin ingin melanjutkan tindakan ini?';
            iconWrapEl.className = v.iconWrap;
            iconEl.className = v.icon;
            submitBtn.className = v.btn;
            submitBtn.textContent = options.confirmText || 'Ya, Lanjutkan';
            cancelBtn.className = options.cancelClass || defaultCancelBtnClass;
            cancelBtn.textContent = options.cancelText || 'Batal';
            pendingCallback = typeof options.onConfirm === 'function' ? options.onConfirm : null;
            pendingCancelCallback = typeof options.onCancel === 'function' ? options.onCancel : null;

            if (!dialog.open) {
                dialog.showModal();
            }
            cancelBtn.focus();
        }

        submitBtn.addEventListener('click', function () {
            const cb = pendingCallback;
            closeModal();
            if (cb) cb();
        });

        cancelBtn.addEventListener('click', function () {
            const cb = pendingCancelCallback;
            closeModal();
            if (cb) cb();
        });
        closeBtn.addEventListener('click', closeModal);

        // Tutup saat klik backdrop di luar kotak modal
        dialog.addEventListener('click', function (e) {
            if (e.target === dialog) closeModal();
        });

        dialog.addEventListener('cancel', function () {
            pendingCallback = null;
            pendingCancelCallback = null;
        });

        // Otomatis tangkap form mana pun yang memakai atribut data-confirm-title
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (!(form instanceof HTMLFormElement)) return;
            const title = form.getAttribute('data-confirm-title');
            if (!title) return;

            e.preventDefault();
            openConfirm({
                title: title,
                message: form.getAttribute('data-confirm-message') || 'Apakah kamu yakin ingin melanjutkan tindakan ini?',
                confirmText: form.getAttribute('data-confirm-btn') || 'Ya, Lanjutkan',
                variant: form.getAttribute('data-confirm-variant') || 'default',
                onConfirm: function () {
                    HTMLFormElement.prototype.submit.call(form);
                }
            });
        });

        // API ringan jika halaman lain ingin memanggil modal langsung dari JS
        window.looteConfirm = openConfirm;
    })();
</script>
