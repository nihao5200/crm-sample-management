/**
 * 客户订单样品管理系统 - 主JavaScript文件
 */

// DOM加载完成后执行
document.addEventListener('DOMContentLoaded', function() {
    // 初始化工具提示
    initTooltips();
    
    // 初始化确认对话框
    initConfirmDialogs();
    
    // 自动隐藏提示消息
    autoHideAlerts();
});

/**
 * 初始化Bootstrap工具提示
 */
function initTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * 初始化确认对话框
 */
function initConfirmDialogs() {
    document.querySelectorAll('[data-confirm]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            var message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
}

/**
 * 自动隐藏提示消息（仅针对闪存消息，不针对页面常驻提醒）
 */
function autoHideAlerts() {
    // 只自动隐藏闪存消息（flash message），不隐藏页面常驻的统计提醒
    var alerts = document.querySelectorAll('.alert.alert-dismissible:not(.alert-permanent)');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
}

/**
 * 格式化日期
 * @param {string} dateString - 日期字符串
 * @param {string} format - 格式
 * @returns {string}
 */
function formatDate(dateString, format) {
    if (!dateString) return '-';
    
    var date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    
    format = format || 'YYYY-MM-DD';
    
    var year = date.getFullYear();
    var month = String(date.getMonth() + 1).padStart(2, '0');
    var day = String(date.getDate()).padStart(2, '0');
    var hours = String(date.getHours()).padStart(2, '0');
    var minutes = String(date.getMinutes()).padStart(2, '0');
    var seconds = String(date.getSeconds()).padStart(2, '0');
    
    return format
        .replace('YYYY', year)
        .replace('MM', month)
        .replace('DD', day)
        .replace('HH', hours)
        .replace('mm', minutes)
        .replace('ss', seconds);
}

/**
 * 格式化金额
 * @param {number} amount - 金额
 * @returns {string}
 */
function formatMoney(amount) {
    if (amount === null || amount === undefined) return '0.00';
    return parseFloat(amount).toFixed(2);
}

/**
 * 表单序列化为对象
 * @param {HTMLFormElement} form - 表单元素
 * @returns {Object}
 */
function serializeForm(form) {
    var formData = new FormData(form);
    var data = {};
    
    formData.forEach(function(value, key) {
        if (data[key]) {
            if (!Array.isArray(data[key])) {
                data[key] = [data[key]];
            }
            data[key].push(value);
        } else {
            data[key] = value;
        }
    });
    
    return data;
}

/**
 * AJAX请求封装
 * @param {string} url - 请求地址
 * @param {Object} options - 配置选项
 * @returns {Promise}
 */
function ajax(url, options) {
    options = options || {};
    var method = (options.method || 'GET').toUpperCase();
    var data = options.data || null;
    var headers = options.headers || {};
    
    return new Promise(function(resolve, reject) {
        var xhr = new XMLHttpRequest();
        
        xhr.open(method, url, true);
        
        // 设置请求头
        Object.keys(headers).forEach(function(key) {
            xhr.setRequestHeader(key, headers[key]);
        });
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } catch (e) {
                        resolve(xhr.responseText);
                    }
                } else {
                    reject(new Error('请求失败: ' + xhr.statusText));
                }
            }
        };
        
        xhr.onerror = function() {
            reject(new Error('网络请求失败'));
        };
        
        if (data && typeof data === 'object') {
            if (data instanceof FormData) {
                xhr.send(data);
            } else {
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.send(JSON.stringify(data));
            }
        } else {
            xhr.send(data);
        }
    });
}

/**
 * 显示加载中
 * @param {HTMLElement} element - 目标元素
 * @param {string} text - 提示文字
 */
function showLoading(element, text) {
    text = text || '加载中...';
    element.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">' + text + '</p></div>';
}

/**
 * 显示空数据提示
 * @param {HTMLElement} element - 目标元素
 * @param {string} text - 提示文字
 */
function showEmpty(element, text) {
    text = text || '暂无数据';
    element.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-inbox" style="font-size: 3rem;"></i><p class="mt-2">' + text + '</p></div>';
}

/**
 * 打印表格
 * @param {string} tableId - 表格ID
 * @param {string} title - 标题
 */
function printTable(tableId, title) {
    title = title || '打印';
    var table = document.getElementById(tableId);
    if (!table) return;
    
    var printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>' + title + '</title>');
    printWindow.document.write('<style>table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background-color:#f5f5f5;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<h2>' + title + '</h2>');
    printWindow.document.write(table.outerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}

/**
 * 导出CSV
 * @param {Array} data - 数据数组
 * @param {string} filename - 文件名
 */
function exportCSV(data, filename) {
    filename = filename || 'export.csv';
    
    if (!data || !data.length) {
        alert('没有数据可导出');
        return;
    }
    
    var csv = [];
    var headers = Object.keys(data[0]);
    csv.push(headers.join(','));
    
    data.forEach(function(row) {
        var values = headers.map(function(header) {
            var value = row[header];
            if (value === null || value === undefined) value = '';
            value = String(value).replace(/"/g, '""');
            if (value.indexOf(',') > -1 || value.indexOf('"') > -1 || value.indexOf('\n') > -1) {
                value = '"' + value + '"';
            }
            return value;
        });
        csv.push(values.join(','));
    });
    
    var blob = new Blob(['\uFEFF' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
}

/**
 * 防抖函数
 * @param {Function} func - 目标函数
 * @param {number} wait - 等待时间
 * @returns {Function}
 */
function debounce(func, wait) {
    var timeout;
    return function() {
        var context = this;
        var args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(function() {
            func.apply(context, args);
        }, wait);
    };
}

/**
 * 节流函数
 * @param {Function} func - 目标函数
 * @param {number} limit - 限制时间
 * @returns {Function}
 */
function throttle(func, limit) {
    var inThrottle;
    return function() {
        var context = this;
        var args = arguments;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(function() {
                inThrottle = false;
            }, limit);
        }
    };
}
