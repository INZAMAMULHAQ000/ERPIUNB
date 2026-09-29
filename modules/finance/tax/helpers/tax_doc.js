/**
 * tax_doc.js — client-side mirror of TaxService::calculateLineTax for
 * document line forms (customer invoices, vendor bills, expenses).
 *
 * A document form sets one global before this file is used:
 *   window.TAX_INCLUSIVE = 0|1          (TaxService::pricesInclusive())
 * and each line row carries:
 *   .tax-doc-rate   select  (options carry data-rate, data-type)
 *   .tax-doc-status select  (taxable | zero_rated | exempt | out_of_scope)
 *   .tax-doc-exemption select
 *   .base-before-tax input  (hidden: qty*price*(1-disc) — for exclusive mode)
 *   .line-tax       output  (readonly tax amount display)
 *   .line-total     output  (readonly grand total display)
 * Document totals come from .doc-subtotal / .doc-discount elements.
 */
(function () {
    'use strict';

    window.TAX_RATE_ID_OF = function (row) {
        var sel = row.querySelector('.tax-doc-rate');
        return sel ? sel.value : '';
    };

    function pctFromOption(sel) {
        var opt = sel.options[sel.selectedIndex];
        return opt ? parseFloat(opt.getAttribute('data-rate')) || 0 : 0;
    }

    function round2(n) {
        return Math.round((n + Number.EPSILON) * 100) / 100;
    }

    function exemptStatus(status) {
        return status === 'exempt' || status === 'zero_rated' || status === 'out_of_scope';
    }

    /** Recompute one line's tax + grand total. Returns {net, tax, total}. */
    window.computeLineTax = function (row) {
        var qty = parseFloat((row.querySelector('.qty-input') || {}).value) || 0;
        var price = parseFloat((row.querySelector('.price-input') || {}).value) || 0;
        var discPct = parseFloat((row.querySelector('.disc-input') || {}).value) || 0;
        var statusSel = row.querySelector('.tax-doc-status');
        var status = statusSel ? statusSel.value : 'taxable';
        var excluded = exemptStatus(status);

        var grossBeforeDiscount = qty * price;
        var discount = grossBeforeDiscount * (discPct / 100);
        var base = grossBeforeDiscount - discount;          // net (exclusive) or gross (inclusive)
        var rate = 0;
        if (!excluded) {
            var rateSel = row.querySelector('.tax-doc-rate');
            rate = rateSel ? pctFromOption(rateSel) : 0;
        }

        var net, tax, total;
        if (window.TAX_INCLUSIVE) {
            // base is the gross (tax-inclusive) line value.
            net = base / (1 + rate / 100);
            net = round2(net);
            tax = round2(base - net);
            total = base;
        } else {
            net = base;
            tax = round2(net * rate / 100);
            total = round2(net + tax);
        }

        // Persist the engine inputs so the server recomputes identically.
        var baseInput = row.querySelector('.base-before-tax');
        if (baseInput) baseInput.value = base.toFixed(2);

        var lineTax = row.querySelector('.line-tax');
        if (lineTax) lineTax.value = tax.toFixed(2);
        var lineTotal = row.querySelector('.line-total');
        if (lineTotal) lineTotal.value = total.toFixed(2);

        // Update per-line taxable (net) display input if present.
        var lineNet = row.querySelector('.line-net');
        if (lineNet) lineNet.value = net.toFixed(2);

        return { net: net, tax: tax, total: total, discount: discount, gross: grossBeforeDiscount };
    };

    /** Recompute the whole items table + document totals. */
    window.recalcDocTotals = function (tableId) {
        var rows = document.querySelectorAll((tableId ? '#' + tableId + ' ' : '') + '.item-row');
        var subtotal = 0, discount = 0, tax = 0, total = 0;
        rows.forEach(function (row) {
            var r = window.computeLineTax(row);
            subtotal += r.gross;      // pre-discount gross (kept for display parity)
            discount += r.discount;
            tax += r.tax;
            total += r.total;
        });

        var fmt = function (v) { return v.toFixed(2); };
        var els = {
            displaySubtotal: fmt(subtotal),
            displayDiscount: fmt(discount),
            displayTax: fmt(tax),
            displayTotal: fmt(total),
        };
        Object.keys(els).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = els[id];
        });

        var hidden = {
            subtotal_hidden: fmt(subtotal),
            discount_hidden: fmt(discount),
            tax_hidden: fmt(tax),
            total_hidden: fmt(total),
        };
        Object.keys(hidden).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = hidden[id];
        });
    };

    window.recalcLineRow = function (row) {
        window.computeLineTax(row);
    };

    /** Wire one row's tax inputs to recalculation. */
    window.wireTaxRow = function (row) {
        var recalcRow = function () {
            window.computeLineTax(row);
            var table = row.closest('table');
            if (table && table.id && typeof window.recalcDocTotals === 'function') {
                window.recalcDocTotals(table.id);
            }
        };
        ['change', 'input'].forEach(function (evt) {
            var sels = row.querySelectorAll('.tax-doc-rate, .tax-doc-status, .tax-doc-exemption');
            for (var i = 0; i < sels.length; i++) {
                sels[i].addEventListener(evt, recalcRow);
            }
            var inputs = row.querySelectorAll('.qty-input, .price-input, .disc-input');
            for (var j = 0; j < inputs.length; j++) {
                inputs[j].addEventListener(evt, recalcRow);
            }
        });
        window.toggleExemptionSelect(row);
    };

    /** Show the exemption picker only when the line status is 'exempt'. */
    window.toggleExemptionSelect = function (row) {
        var statusSel = row.querySelector('.tax-doc-status');
        var expSel = row.querySelector('.tax-doc-exemption');
        if (!statusSel || !expSel) return;
        expSel.hidden = statusSel.value !== 'exempt';
    };
})();