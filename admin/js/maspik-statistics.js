jQuery(document).ready(function($) {
    'use strict';

    // ── Toast notification helper ─────────────────────────────────────────
    if (!$('#maspik-toast-wrap').length) {
        $('body').append('<div id="maspik-toast-wrap" class="maspik-toast-wrap"></div>');
    }
    function maspikToast(message, type) {
        type = type || 'success';
        var icons = { success: 'yes-alt', error: 'dismiss', warning: 'warning', info: 'info' };
        var $t = $('<div class="maspik-toast maspik-toast--' + type + '">' +
            '<span class="dashicons dashicons-' + (icons[type] || 'info') + '"></span>' +
            '<span>' + message + '</span></div>');
        $('#maspik-toast-wrap').append($t);
        setTimeout(function () { $t.addClass('maspik-toast--visible'); }, 10);
        setTimeout(function () {
            $t.removeClass('maspik-toast--visible');
            setTimeout(function () { $t.remove(); }, 300);
        }, 4000);
    }

    // ── Confirm modal helper (replaces native confirm() dialogs) ──────────
    if (!$('#maspik-confirm-modal').length) {
        $('body').append(
            '<div id="maspik-confirm-modal">' +
              '<div class="maspik-confirm-box">' +
                '<span class="dashicons maspik-confirm-icon"></span>' +
                '<p id="maspik-confirm-msg"></p>' +
                '<div class="maspik-confirm-actions">' +
                  '<button class="maspik-confirm-btn maspik-confirm-btn--ok" id="maspik-confirm-ok">Confirm</button>' +
                  '<button class="maspik-confirm-btn maspik-confirm-btn--cancel" id="maspik-confirm-cancel">Cancel</button>' +
                '</div>' +
              '</div>' +
            '</div>'
        );
        $(document).on('click', '#maspik-confirm-modal', function (e) {
            if (e.target === this) { $(this).removeClass('is-open'); }
        });
        $(document).on('click', '#maspik-confirm-cancel', function () {
            $('#maspik-confirm-modal').removeClass('is-open');
        });
    }
    function maspikConfirm(message, onOk, opts) {
        opts = opts || {};
        $('#maspik-confirm-msg').text(message);
        var $icon = $('#maspik-confirm-modal .maspik-confirm-icon');
        $icon.attr('class', 'dashicons maspik-confirm-icon ' +
            (opts.danger ? 'dashicons-trash maspik-confirm-icon--danger' : 'dashicons-warning maspik-confirm-icon--warn'));
        var $ok = $('#maspik-confirm-ok');
        $ok.attr('class', 'maspik-confirm-btn ' + (opts.danger ? 'maspik-confirm-btn--ok-danger' : 'maspik-confirm-btn--ok'));
        $ok.text(opts.okLabel || 'Confirm');
        $('#maspik-confirm-modal').addClass('is-open');
        $ok.off('click.maspikConfirm').on('click.maspikConfirm', function () {
            $('#maspik-confirm-modal').removeClass('is-open');
            if (typeof onOk === 'function') { onOk(); }
        });
    }

    // Handle custom date range selector
    $('.nav-tab-wrapper .nav-tab').on('click', function(e) {
        if ($(this).data('range') === 'custom') {
            e.preventDefault();
            $('#custom-date-range').show();
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
        } else {
            $('#custom-date-range').hide();
        }
    });

    // Initialize charts if library is loaded
    if (typeof Chart !== 'undefined') {
        // Chart initialization code here
    }

    // Initialize maps if library is loaded
    if (typeof $.fn.vectorMap !== 'undefined') {
        // Vector map initialization code here
    }

    // Create Timeline Chart
    if ($('#timelineChart').length && typeof Chart !== 'undefined' && maspikStats.timelineData && maspikStats.timelineData.length) {
        const timelineCtx = document.getElementById('timelineChart').getContext('2d');
        
        // Format dates for display
        const formattedData = maspikStats.timelineData.map(item => {
            // Try to identify the date format and format it accordingly
            const date = new Date(item.period);
            if (!isNaN(date)) {
                return {
                    period: date.toLocaleDateString(),
                    count: item.count
                };
            }
            return item;
        });


        new Chart(timelineCtx, {
            type: 'line',
            data: {
                labels: formattedData.map(item => item.period),
                datasets: [{
                    label: maspikStats.translations.blockedSpam,
                    data: formattedData.map(item => item.count),
                    borderColor: '#014921',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${maspikStats.translations.blockedSpam}: ${context.parsed.y}`;
                            }
                        }
                    }
                }
            }
        });
    } else {
        if ( typeof maspikStatsDebug !== 'undefined' && maspikStatsDebug ) { console.log( 'Timeline chart not created - missing data or dependencies' ); }
    }

    // Create Countries Chart
    if ($('#countriesChart').length && typeof Chart !== 'undefined' && maspikStats.countriesData && maspikStats.countriesData.length) {
        const countriesCtx = document.getElementById('countriesChart').getContext('2d');
        new Chart(countriesCtx, {
            type: 'doughnut',
            data: {
                labels: maspikStats.countriesData.map(item => item.country),
                datasets: [{
                    data: maspikStats.countriesData.map(item => item.count),
                    backgroundColor: [
                        '#F48722', '#e14d43', '#46b450', '#ffb900', '#826eb4',
                        '#00a0d2', '#f1c40f', '#e67e22', '#2ecc71', '#3498db'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const item = maspikStats.countriesData[context.dataIndex];
                                return `${item.country}: ${item.count} (${item.percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    } else {
        if ( typeof maspikStatsDebug !== 'undefined' && maspikStatsDebug ) { console.log( 'Countries chart not created - missing data or dependencies' ); }
    }

    // Create Sources Chart
    if ($('#sourcesChart').length && typeof Chart !== 'undefined' && maspikStats.sourcesData && maspikStats.sourcesData.length) {
        const sourcesCtx = document.getElementById('sourcesChart').getContext('2d');
        new Chart(sourcesCtx, {
            type: 'bar',
            data: {
                labels: maspikStats.sourcesData.map(item => item.source),
                datasets: [{
                    label: maspikStats.translations.spamAttempts,
                    data: maspikStats.sourcesData.map(item => item.count),
                    backgroundColor: '#F48722'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    } else {
        if ( typeof maspikStatsDebug !== 'undefined' && maspikStatsDebug ) { console.log( 'Sources chart not created - missing data or dependencies' ); }
    }

    // Create World Map Chart
    if ($('#worldMapChart').length && maspikStats.countriesData && maspikStats.countriesData.length) {
        if (typeof google === 'undefined' || typeof google.charts === 'undefined') {
            $('#worldMapChart').html('<div class="notice notice-error"><p>Google Charts API not loaded. Please make sure the script is included.</p></div>');
        } else {
            google.charts.load('current', {
                'packages': ['geochart'],
                'mapsApiKey': '' // No need for API key for basic usage
            });
            
            google.charts.setOnLoadCallback(drawRegionsMap);
            
            function drawRegionsMap() {
                try {
                    
                    const mapData = [['Country', 'Spam Count', {role: 'tooltip', p:{html:true}}]];
                    // Deduplicated country → ISO-3166-1-alpha-2 map.
                    const countryCodeMap = {
                        'Unknown':        'XX',
                        'United States':  'US',
                        'United Kingdom': 'GB',
                        'Russia':         'RU',
                        'Germany':        'DE',
                        'France':         'FR',
                        'Canada':         'CA',
                        'Australia':      'AU',
                        'South Korea':    'KR',
                        'Korea':          'KR',
                        'Italy':          'IT',
                        'Spain':          'ES',
                        'Israel':         'IL',
                        'China':          'CN',
                        'India':          'IN',
                        'Brazil':         'BR',
                        'Japan':          'JP',
                        'Mexico':         'MX',
                        'Indonesia':      'ID',
                        'Turkey':         'TR',
                        'Netherlands':    'NL',
                        'Saudi Arabia':   'SA',
                        'Switzerland':    'CH',
                        'South Africa':   'ZA',
                        'Ukraine':        'UA',
                        'Poland':         'PL',
                        'Sweden':         'SE',
                        'Belgium':        'BE',
                        'Argentina':      'AR',
                        'Thailand':       'TH',
                        'Egypt':          'EG',
                        'Pakistan':       'PK',
                        'Vietnam':        'VN',
                        'Iran':           'IR',
                        'Iraq':           'IQ',
                    };
                    
                    let totalSpam = 0;
                    maspikStats.countriesData.forEach(item => {
                        totalSpam += parseInt(item.count);
                    });
                    
                    let maxValue = 0;
                    let minValue = Infinity;
                    
                    maspikStats.countriesData.forEach(item => {
                        const count = parseInt(item.count);
                        if (count > maxValue) maxValue = count;
                        if (count < minValue && count > 0) minValue = count;
                        
                        const code = countryCodeMap[item.country] || item.country;
                        const percentage = ((count / totalSpam) * 100).toFixed(1);
                        
                        const tooltip = `
                            <div style="padding:10px;max-width:200px;">
                                <div style="font-weight:bold;border-bottom:1px solid #ddd;margin-bottom:5px;padding-bottom:5px;">
                                    ${item.country}
                                </div>
                                <div style="display:flex;justify-content:space-between;margin-bottom:3px;">
                                    <span>Spam Count:</span>
                                    <span style="font-weight:bold;">${count.toLocaleString()}</span>
                                </div>
                                <div style="display:flex;justify-content:space-between;">
                                    <span>Percentage:</span>
                                    <span style="font-weight:bold;">${percentage}%</span>
                                </div>
                            </div>
                        `;
                        
                        mapData.push([code, count, tooltip]);
                    });
                    
                    let colorRange;
                    
                    if (maxValue <= 10) {
                        colorRange = ['#E3F2FD', '#1565C0'];
                    } else if (maxValue <= 100) {
                        colorRange = ['#E3F2FD', '#90CAF9', '#2196F3', '#1565C0'];
                    } else {
                        colorRange = ['#E3F2FD', '#90CAF9', '#42A5F5', '#1E88E5', '#1565C0', '#0D47A1'];
                    }
                    
                    const options = {
                        colorAxis: {
                            colors: colorRange,
                            minValue: 1,
                            values: maxValue > 1000 ? [1, 10, 100, 1000, maxValue] : null
                        },
                        backgroundColor: 'transparent',
                        datalessRegionColor: '#F8F9F9',
                        defaultColor: '#F8F9F9',
                        legend: {
                            textStyle: { color: '#333', fontSize: 12 }
                        },
                        tooltip: { isHtml: true },
                        width: '100%',
                        height: '100%',
                        keepAspectRatio: true,
                        forceIFrame: false,
                    };
                    
                    // Create chart
                    const data = google.visualization.arrayToDataTable(mapData);
                    const chart = new google.visualization.GeoChart(document.getElementById('worldMapChart'));
                    chart.draw(data, options);

                    // Adjust map container height after chart creation
                    setTimeout(function() {
                        const chartHeight = $('#worldMapChart').find('div:first').height();
                        if (chartHeight > 0) {
                            $('#worldMapChart').height(chartHeight);
                            $('.chart-container').css('min-height', (chartHeight + 40) + 'px');
                        }
                    }, 500); // Short delay to allow map to load
                } catch (error) {
                    console.error('Error creating GeoChart:', error);
                    $('#worldMapChart').html(`<div class="notice notice-error"><p>Error creating map: ${error.message}</p></div>`);
                }
            }
        }
    } else {
        if ( typeof maspikStatsDebug !== 'undefined' && maspikStatsDebug ) { console.log( 'GeoChart not created - missing data or dependencies' ); }
        if ($('#worldMapChart').length) {
            $('#worldMapChart').html('<div class="notice notice-warning"><p>Missing data for geographical visualization.</p></div>');
        }
    }

    // Handle Block IP functionality
    $('.block-ip').on('click', function() {
        const ip = $(this).data('ip');
        maspikConfirm('Block IP address ' + ip + '?', function () {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { action: 'maspik_block_ip', nonce: maspikStats.nonce, ip: ip },
                success: function(response) {
                    if (response.success) {
                        maspikToast('IP ' + ip + ' blocked successfully.', 'success');
                        setTimeout(function () { location.reload(); }, 1200);
                    } else {
                        maspikToast('Failed to block IP: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
                    }
                },
                error: function() { maspikToast('Server error. Please try again.', 'error'); }
            });
        }, { danger: true, okLabel: 'Block IP' });
    });

    // Handle Block Selected IPs
    $('#block-selected-ips').on('click', function() {
        const selectedIps = $('input[name="selected_ips[]"]:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedIps.length === 0) {
            maspikToast('Please select at least one IP to block.', 'warning');
            return;
        }

        maspikConfirm('Block ' + selectedIps.length + ' selected IP address' + (selectedIps.length > 1 ? 'es' : '') + '?', function () {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { action: 'maspik_block_multiple_ips', nonce: maspikStats.nonce, ips: selectedIps },
                success: function(response) {
                    if (response.success) {
                        maspikToast(response.data.count + ' IPs blocked successfully.', 'success');
                        setTimeout(function () { location.reload(); }, 1200);
                    } else {
                        maspikToast('Failed to block IPs: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
                    }
                },
                error: function() { maspikToast('Server error. Please try again.', 'error'); }
            });
        }, { danger: true, okLabel: 'Block IPs' });
    });

    // Handle Select All IPs checkbox
    $('#select-all-ips').on('change', function() {
        $('input[name="selected_ips[]"]').prop('checked', $(this).prop('checked'));
    });

    // Handle Block Country functionality
    $('.block-country').on('click', function() {
        const country = $(this).data('country');
        maspikConfirm('Block country "' + country + '"?', function () {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { action: 'maspik_block_country', nonce: maspikStats.nonce, country: country },
                success: function(response) {
                    if (response.success) {
                        maspikToast('Country "' + country + '" blocked successfully.', 'success');
                        setTimeout(function () { location.reload(); }, 1200);
                    } else {
                        if (response.data && response.data.wrong_mode) {
                            maspikConfirm(response.data.message + ' Go to Settings?', function () {
                                window.location.href = response.data.settings_url;
                            }, { okLabel: 'Go to Settings' });
                        } else if (response.data && response.data.is_pro === false) {
                            showProFeatureModal('country_blocking');
                        } else {
                            maspikToast('Failed to block country: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
                        }
                    }
                },
                error: function() { maspikToast('Server error. Please try again.', 'error'); }
            });
        }, { danger: true, okLabel: 'Block Country' });
    });

    // Function to convert country name to country code
    function getCountryCode(countryName) {
        // If already a valid 2-letter country code, return it as is
        if (/^[A-Z]{2}$/.test(countryName)) {
            return countryName;
        }
        
        // Use the list of countries we received from the server
        if (maspikStats && maspikStats.countryCodes) {
            // Direct lookup
            if (maspikStats.countryCodes[countryName]) {
                return maspikStats.countryCodes[countryName];
            }
            
            // Lookup without case sensitivity
            for (let country in maspikStats.countryCodes) {
                if (country.toLowerCase() === countryName.toLowerCase()) {
                    return maspikStats.countryCodes[country];
                }
            }
            
            // Reverse lookup (check if countryName is actually a country code)
            const flippedMap = {};
            Object.keys(maspikStats.countryCodes).forEach(country => {
                flippedMap[maspikStats.countryCodes[country]] = country;
            });
            
            if (flippedMap[countryName]) {
                return countryName;
            }
        }
        
        // If no match found, revert to legacy mapping
        const countryMap = {
            'Afghanistan': 'AF',
            'Albania': 'AL',
            'Algeria': 'DZ',
            'Andorra': 'AD',
            'Angola': 'AO',
            'Argentina': 'AR',
            'Armenia': 'AM',
            'Australia': 'AU',
            'Austria': 'AT',
            'Azerbaijan': 'AZ',
            'Bahamas': 'BS',
            'Bahrain': 'BH',
            'Bangladesh': 'BD',
            'Belarus': 'BY',
            'Belgium': 'BE',
            'Brazil': 'BR',
            'Bulgaria': 'BG',
            'Canada': 'CA',
            'Chile': 'CL',
            'China': 'CN',
            'Colombia': 'CO',
            'Croatia': 'HR',
            'Cuba': 'CU',
            'Cyprus': 'CY',
            'Czech Republic': 'CZ',
            'Denmark': 'DK',
            'Egypt': 'EG',
            'Estonia': 'EE',
            'Finland': 'FI',
            'France': 'FR',
            'Georgia': 'GE',
            'Germany': 'DE',
            'Greece': 'GR',
            'Hong Kong': 'HK',
            'Hungary': 'HU',
            'Iceland': 'IS',
            'India': 'IN',
            'Indonesia': 'ID',
            'Iran': 'IR',
            'Iraq': 'IQ',
            'Ireland': 'IE',
            'Israel': 'IL',
            'Italy': 'IT',
            'Japan': 'JP',
            'Jordan': 'JO',
            'Kazakhstan': 'KZ',
            'Kuwait': 'KW',
            'Latvia': 'LV',
            'Lebanon': 'LB',
            'Libya': 'LY',
            'Lithuania': 'LT',
            'Luxembourg': 'LU',
            'Malaysia': 'MY',
            'Malta': 'MT',
            'Mexico': 'MX',
            'Morocco': 'MA',
            'Netherlands': 'NL',
            'New Zealand': 'NZ',
            'Nigeria': 'NG',
            'Norway': 'NO',
            'Pakistan': 'PK',
            'Palestine': 'PS',
            'Peru': 'PE',
            'Philippines': 'PH',
            'Poland': 'PL',
            'Portugal': 'PT',
            'Qatar': 'QA',
            'Romania': 'RO',
            'Russia': 'RU',
            'Saudi Arabia': 'SA',
            'Serbia': 'RS',
            'Singapore': 'SG',
            'Slovakia': 'SK',
            'Slovenia': 'SI',
            'South Africa': 'ZA',
            'South Korea': 'KR',
            'Spain': 'ES',
            'Sweden': 'SE',
            'Switzerland': 'CH',
            'Syria': 'SY',
            'Taiwan': 'TW',
            'Thailand': 'TH',
            'Tunisia': 'TN',
            'Turkey': 'TR',
            'Ukraine': 'UA',
            'United Arab Emirates': 'AE',
            'United Kingdom': 'GB',
            'United States': 'US',
            'Vietnam': 'VN',
            'Unknown': 'XX'
        };
        
        // Search in country names in legacy mapping
        if (countryMap[countryName]) {
            return countryMap[countryName];
        }
        
        // Reverse lookup in legacy mapping
        const flippedLegacyMap = {};
        Object.keys(countryMap).forEach(country => {
            flippedLegacyMap[countryMap[country]] = country;
        });
        
        if (flippedLegacyMap[countryName]) {
            return countryName;
        }
        
        // No match found
        return countryName; // Return original value instead of null
    }

    // Handle Block Selected Countries
    $('#block-selected-countries').on('click', function() {
        const selectedCountries = $('input[name="selected_countries[]"]:checked').map(function() {
            const countryName = $(this).val();
            return getCountryCode(countryName) || countryName;
        }).get();

        if (selectedCountries.length === 0) {
            maspikToast('Please select at least one country to block.', 'warning');
            return;
        }

        maspikConfirm('Block ' + selectedCountries.length + ' selected countr' + (selectedCountries.length > 1 ? 'ies' : 'y') + '?', function () {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { action: 'maspik_block_multiple_countries', nonce: maspikStats.nonce, countries: selectedCountries },
                success: function(response) {
                    if (response.success) {
                        maspikToast(response.data.count + ' countries blocked successfully.', 'success');
                        setTimeout(function () { location.reload(); }, 1200);
                    } else {
                        if (response.data && response.data.wrong_mode) {
                            maspikConfirm(response.data.message + ' Go to Settings?', function () {
                                window.location.href = response.data.settings_url;
                            }, { okLabel: 'Go to Settings' });
                        } else if (response.data && response.data.is_pro === false) {
                            showProFeatureModal('country_blocking');
                        } else {
                            maspikToast('Failed to block countries: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
                        }
                    }
                },
                error: function() { maspikToast('Server error. Please try again.', 'error'); }
            });
        }, { danger: true, okLabel: 'Block Countries' });
    });

    // Handle Select All Countries checkbox
    $('#select-all-countries').on('change', function() {
        $('input[name="selected_countries[]"]').prop('checked', $(this).prop('checked'));
    });

    // Handle Block Domain functionality
    $('.block-domain').on('click', function() {
        const domain = $(this).data('domain');
        maspikConfirm('Block all emails from "@' + domain + '"?', function () {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { action: 'maspik_block_domain', nonce: maspikStats.nonce, domain: domain },
                success: function(response) {
                    if (response.success) {
                        maspikToast('@' + domain + ' blocked successfully.', 'success');
                        setTimeout(function () { location.reload(); }, 1200);
                    } else {
                        maspikToast('Failed to block domain: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
                    }
                },
                error: function() { maspikToast('Server error. Please try again.', 'error'); }
            });
        }, { danger: true, okLabel: 'Block Domain' });
    });

    // Handle Block Selected Domains
    $('#block-selected-domains').on('click', function() {
        const selectedDomains = $('input[name="selected_domains[]"]:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedDomains.length === 0) {
            maspikToast('Please select at least one domain to block.', 'warning');
            return;
        }

        maspikConfirm('Block all emails from ' + selectedDomains.length + ' selected domain' + (selectedDomains.length > 1 ? 's' : '') + '?', function () {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { action: 'maspik_block_multiple_domains', nonce: maspikStats.nonce, domains: selectedDomains },
                success: function(response) {
                    if (response.success) {
                        maspikToast(response.data.count + ' domains blocked successfully.', 'success');
                        setTimeout(function () { location.reload(); }, 1200);
                    } else {
                        maspikToast('Failed to block domains: ' + (response.data ? response.data.message : 'Unknown error'), 'error');
                    }
                },
                error: function() { maspikToast('Server error. Please try again.', 'error'); }
            });
        }, { danger: true, okLabel: 'Block Domains' });
    });

    // Handle Select All Domains checkbox
    $('#select-all-domains').on('change', function() {
        $('input[name="selected_domains[]"]').prop('checked', $(this).prop('checked'));
    });

    // Function to show the Pro Feature modal
    function showProFeatureModal(feature) {
        // Check if modal HTML exists already
        if (!$('#pro-feature-modal').length) {
            // Create modal HTML
            const modalHtml = `
                <div id="pro-feature-modal" class="maspik-modal">
                    <div class="maspik-modal-content">
                        <div class="maspik-modal-header">
                            <span class="maspik-modal-close">&times;</span>
                            <h2>MASPIK Pro Features</h2>
                        </div>
                        <div class="maspik-modal-body">
                            <div class="maspik-pro-feature">
                                <div class="maspik-pro-feature-icon">
                                    <span class="dashicons dashicons-shield"></span>
                                </div>
                                <div class="maspik-pro-feature-content">
                                    <h3 id="pro-feature-title">Enhance Your Website Protection</h3>
                                    <p id="pro-feature-message">Upgrade to MASPIK Pro and get access to powerful anti-spam features:</p>
                                    <ul>
                                        <li>Custom spam API for multiple sites</li>
                                        <li>Country-based filtering</li>
                                        <li>Language detection & blocking</li>
                                        <li>Premium support</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="maspik-modal-footer">
                            <button type="button" class="button button-secondary maspik-modal-close">Maybe Later</button>
                            <a target="_blank" href="https://wpmaspik.com/?ref=getpro" 
                               class="button button-primary">Upgrade to Pro</a>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            
            // Attach close events
            $('.maspik-modal-close').on('click', function() {
                $('#pro-feature-modal').hide();
            });
            
            // Close when clicking outside of modal
            $(window).on('click', function(event) {
                if ($(event.target).is('.maspik-modal')) {
                    $('.maspik-modal').hide();
                }
            });
        }
        
        // Update feature-specific title and message
        let featureTitle = 'Enhance Your Website Protection';
        let featureMessage = 'Upgrade to MASPIK Pro and get access to powerful anti-spam features:';
        
        // Match the title and message to the feature
        if (feature === 'country_blocking') {
            featureTitle = 'Advanced Country Blocking';
            featureMessage = 'Protect your website from spam originating from specific countries:';
            
            // Update icon
            $('.maspik-pro-feature-icon .dashicons')
                .removeClass('dashicons-shield')
                .addClass('dashicons-admin-site');
        } else {
            // Reset to default icon for other features
            $('.maspik-pro-feature-icon .dashicons')
                .removeClass('dashicons-admin-site')
                .addClass('dashicons-shield');
        }
        
        // Update the dynamic content
        $('#pro-feature-title').text(featureTitle);
        $('#pro-feature-message').text(featureMessage);
        
        // Show the modal with animation
        $('#pro-feature-modal').show();
    }

   
});