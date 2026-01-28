document.addEventListener('DOMContentLoaded', function () {
    let filterForm = document.getElementById('ff-filter-form');
    let productsContainer = document.querySelector('.products-container'); // AJAX container

    if (!filterForm || !productsContainer) return;

    function attachEvents() {
        const container = document.querySelector('.ff-filter-container');
        const overlay = document.querySelector('.ff-overlay');
        const floatingBtn = document.querySelector('.ff-mobile-floating-btn');

        // Mở Master Sheet từ Nút Floating (Mobile)
        if (floatingBtn) {
            floatingBtn.addEventListener('click', function () {
                container.classList.add('ff-sheet-open');
                document.body.style.overflow = 'hidden';
            });
        }

        // Xử lý lựa chọn trong dropdown tùy chỉnh
        filterForm.querySelectorAll('.ff-dropdown-list li').forEach(li => {
            li.addEventListener('click', function () {
                const dropdown = this.closest('.ff-dropdown');
                const hiddenInput = dropdown.querySelector('input[type="hidden"]');
                const labelSpan = dropdown.querySelector('.ff-dropdown-label span');

                hiddenInput.value = this.dataset.value;
                if (labelSpan) labelSpan.textContent = this.textContent;

                dropdown.querySelectorAll('li').forEach(item => item.classList.remove('is-active'));
                this.classList.add('is-active');

                // Trên desktop: Trigger lọc luôn
                if (window.innerWidth >= 768) {
                    triggerFilter();
                } else {
                    // Cập nhật UI group active
                    if (this.dataset.value) {
                        dropdown.classList.add('ff-active');
                    } else {
                        dropdown.classList.remove('ff-active');
                    }
                }
            });
        });

        // Xử lý click cho label (chỉ cho desktop)
        filterForm.querySelectorAll('.ff-dropdown-label').forEach(label => {
            label.addEventListener('click', function (e) {
                if (window.innerWidth < 768) return; // Mobile dùng master sheet

                e.stopPropagation();
                const dropdown = this.closest('.ff-dropdown');
                const isOpen = dropdown.classList.contains('ff-is-open');

                // Đóng tất cả các cái khác
                filterForm.querySelectorAll('.ff-dropdown').forEach(d => {
                    d.classList.remove('ff-is-open');
                });

                if (!isOpen) {
                    dropdown.classList.add('ff-is-open');
                }
            });
        });

        // Đóng sheet trên mobile & Áp dụng lọc
        filterForm.querySelectorAll('.ff-close-sheet').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                // Nếu là nút "Áp dụng" (ff-apply-btn)
                if (this.classList.contains('ff-apply-btn')) {
                    triggerFilter();
                }

                closeAllSheets();
            });
        });

        // Hỗ trợ click trực tiếp vào nút Apply (đôi khi class ff-close-sheet bị ghi đè hoặc nhầm lẫn)
        const applyBtn = filterForm.querySelector('.ff-apply-btn');
        if (applyBtn) {
            applyBtn.addEventListener('click', function (e) {
                e.preventDefault();
                triggerFilter();
                closeAllSheets();
            });
        }

        // Reset bộ lọc trong sheet (Mobile)
        const resetSheetBtn = filterForm.querySelector('.ff-reset-btn-sheet');
        if (resetSheetBtn) {
            resetSheetBtn.addEventListener('click', function (e) {
                e.preventDefault();
                filterForm.reset();
                filterForm.querySelectorAll('input[type="hidden"]').forEach(input => {
                    // Không xóa s và post_type
                    if (input.name !== 's' && input.name !== 'post_type') {
                        input.value = '';
                    }
                });
                filterForm.querySelectorAll('li').forEach(li => li.classList.remove('is-active'));
                filterForm.querySelectorAll('.ff-swatch').forEach(s => s.classList.remove('is-active'));
                filterForm.querySelectorAll('.ff-dropdown').forEach(d => d.classList.remove('ff-active'));

                triggerFilter();
                closeAllSheets();
            });
        }

        // Click overlay để đóng
        if (overlay) {
            overlay.addEventListener('click', closeAllSheets);
        }

        // Lắng nghe sự kiện thay đổi trên các input (chỉ cho checkbox màu)
        filterForm.querySelectorAll('input[type="checkbox"]').forEach(input => {
            input.addEventListener('change', function () {
                const group = this.closest('.ff-swatch-group');
                if (this.nextElementSibling && this.nextElementSibling.classList.contains('ff-swatch')) {
                    this.nextElementSibling.classList.toggle('is-active', this.checked);
                }

                // Cập nhật label cho chip màu sắc
                if (group) {
                    const checkedCount = group.querySelectorAll('input[type="checkbox"]:checked').length;
                    const labelSpan = group.querySelector('.ff-dropdown-label span');
                    if (labelSpan) {
                        const baseLabel = labelSpan.dataset.original || labelSpan.textContent.replace(/\s\(\d+\)$/, '');

                        if (!labelSpan.dataset.original) {
                            labelSpan.dataset.original = baseLabel;
                        }

                        if (checkedCount > 0) {
                            labelSpan.textContent = `${baseLabel} (${checkedCount})`;
                            group.classList.add('ff-active');
                        } else {
                            labelSpan.textContent = baseLabel;
                            group.classList.remove('ff-active');
                        }
                    }
                }

                // Trên desktop lọc luôn
                if (window.innerWidth >= 768) {
                    triggerFilter();
                }
            });
        });

        // Lọc khi nhấn Reset button (Desktop)
        const resetBtn = filterForm.querySelector('.ff-reset-btn');
        if (resetBtn) {
            resetBtn.addEventListener('click', function (e) {
                e.preventDefault();
                const resetUrl = this.getAttribute('href');
                updateContent(resetUrl);
            });
        }
    }

    function closeAllSheets() {
        const container = document.querySelector('.ff-filter-container');
        container.classList.remove('ff-sheet-open');
        document.body.style.overflow = '';
    }

    // Khởi tạo sự kiện lần đầu
    attachEvents();

    // Đóng dropdown khi click ra ngoài (Desktop)
    document.addEventListener('click', function (e) {
        if (window.innerWidth >= 768) {
            if (!e.target.closest('.ff-dropdown')) {
                filterForm.querySelectorAll('.ff-dropdown').forEach(d => {
                    d.classList.remove('ff-is-open');
                });
            }
        }
    });

    function triggerFilter() {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams();

        for (const [key, value] of formData.entries()) {
            if (value) {
                params.append(key, value);
            }
        }

        const baseUrl = filterForm.getAttribute('action');
        const sep = baseUrl.includes('?') ? '&' : '?';
        const finalUrl = baseUrl + (params.toString() ? sep + params.toString() : '');

        updateContent(finalUrl);
    }

    function updateContent(url) {
        window.history.pushState({}, '', url);
        productsContainer.classList.add('ff-loading');

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                const newProducts = doc.querySelector('.products-container');
                if (newProducts) {
                    productsContainer.innerHTML = newProducts.innerHTML;
                }

                const newForm = doc.getElementById('ff-filter-form');
                if (newForm) {
                    filterForm.innerHTML = newForm.innerHTML;
                    attachEvents();
                }

                productsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                jQuery(document.body).trigger('post-load');
            })
            .catch(error => console.error('Filter Error:', error))
            .finally(() => {
                productsContainer.classList.remove('ff-loading');
            });
    }

    window.addEventListener('popstate', function () {
        updateContent(window.location.href);
    });
});
