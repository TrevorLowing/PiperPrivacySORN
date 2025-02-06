/* global jQuery, wp, piperPrivacySorn */
(function($) {
    'use strict';

    const SornManager = {
        init() {
            this.bindEvents();
            this.loadSorns();
            this.loadStats();
            this.loadAgencies();
        },

        bindEvents() {
            $('#agency-filter').on('change', () => this.loadSorns());
            $('#status-filter').on('change', () => this.loadSorns());
            $('#search-input').on('input', $.debounce(300, () => this.loadSorns()));
            
            $('.piper-privacy-sorn-pagination').on('click', 'a', (e) => {
                e.preventDefault();
                const $link = $(e.currentTarget);
                if ($link.hasClass('disabled')) return;
                
                if ($link.hasClass('first-page')) this.currentPage = 1;
                else if ($link.hasClass('last-page')) this.currentPage = this.totalPages;
                else if ($link.hasClass('prev-page')) this.currentPage--;
                else if ($link.hasClass('next-page')) this.currentPage++;
                
                this.loadSorns();
            });
        },

        async loadSorns() {
            const params = {
                page: this.currentPage,
                per_page: 20,
                agency: $('#agency-filter').val(),
                status: $('#status-filter').val(),
                search: $('#search-input').val()
            };

            try {
                const response = await wp.apiRequest({
                    path: '/piper-privacy-sorn/v1/sorns',
                    data: params
                });

                this.totalPages = Math.ceil(response.total / params.per_page);
                this.updatePagination();
                this.renderSorns(response.items);
            } catch (error) {
                console.error('Error loading SORNs:', error);
                this.showError('Failed to load SORNs');
            }
        },

        async loadStats() {
            try {
                const stats = await wp.apiRequest({
                    path: '/piper-privacy-sorn/v1/stats'
                });

                $('#total-sorns').text(stats.total);
                $('#pending-review').text(stats.pending);
                $('#published-sorns').text(stats.published);
                $('#fr-submissions').text(stats.federal_register);
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        },

        async loadAgencies() {
            try {
                const agencies = await wp.apiRequest({
                    path: '/piper-privacy-sorn/v1/agencies'
                });

                const $select = $('#agency-filter');
                agencies.forEach(agency => {
                    $select.append(
                        $('<option>', {
                            value: agency.id,
                            text: agency.name
                        })
                    );
                });
            } catch (error) {
                console.error('Error loading agencies:', error);
            }
        },

        renderSorns(sorns) {
            const $tbody = $('#sorn-list');
            $tbody.empty();

            if (!sorns.length) {
                $tbody.append(
                    $('<tr>').append(
                        $('<td>', {
                            colspan: 5,
                            text: 'No SORNs found.'
                        })
                    )
                );
                return;
            }

            sorns.forEach(sorn => {
                const $row = $('<tr>');
                
                // Title column with actions
                const $titleCell = $('<td>', { class: 'title column-title has-row-actions column-primary' });
                $titleCell.append(
                    $('<strong>').append(
                        $('<a>', {
                            href: `admin.php?page=piper-privacy-sorn-edit&id=${sorn.id}`,
                            class: 'row-title',
                            text: sorn.title
                        })
                    )
                );
                
                const $actions = $('<div>', { class: 'row-actions' });
                $actions.append(
                    $('<span>', { class: 'edit' }).append(
                        $('<a>', {
                            href: `admin.php?page=piper-privacy-sorn-edit&id=${sorn.id}`,
                            text: 'Edit'
                        })
                    ),
                    ' | ',
                    $('<span>', { class: 'view' }).append(
                        $('<a>', {
                            href: sorn.permalink,
                            text: 'View'
                        })
                    )
                );
                
                if (sorn.status === 'published') {
                    $actions.append(
                        ' | ',
                        $('<span>', { class: 'fr-submit' }).append(
                            $('<a>', {
                                href: '#',
                                'data-id': sorn.id,
                                text: 'Submit to Federal Register'
                            })
                        )
                    );
                }
                
                $titleCell.append($actions);
                $row.append($titleCell);
                
                // Other columns
                $row.append(
                    $('<td>', { text: sorn.agency }),
                    $('<td>', { text: sorn.system_number }),
                    $('<td>').append(
                        $('<span>', {
                            class: `status-${sorn.status}`,
                            text: this.formatStatus(sorn.status)
                        })
                    ),
                    $('<td>', { text: this.formatDate(sorn.updated_at) })
                );
                
                $tbody.append($row);
            });
        },

        updatePagination() {
            const $pagination = $('.piper-privacy-sorn-pagination');
            
            $('.total-pages').text(this.totalPages);
            $('#current-page-selector').val(this.currentPage);
            
            $('.first-page, .prev-page').toggleClass('disabled', this.currentPage === 1);
            $('.last-page, .next-page').toggleClass('disabled', this.currentPage === this.totalPages);
        },

        formatStatus(status) {
            const statusMap = {
                draft: 'Draft',
                review: 'In Review',
                published: 'Published'
            };
            return statusMap[status] || status;
        },

        formatDate(date) {
            return new Date(date).toLocaleDateString();
        },

        showError(message) {
            // TODO: Implement error notification
            console.error(message);
        },

        currentPage: 1,
        totalPages: 0
    };

    $(document).ready(() => SornManager.init());
})(jQuery);
