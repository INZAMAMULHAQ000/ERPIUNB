<?php
/**
 * Shared compliance + right-to-work block for the contractor create/edit form.
 * Included by form.php, which sets:
 *
 *   $isEdit      bool   edit mode (drives the current-file column)
 *   $contractor  array  the row being edited (or defaults for a new one)
 *   $slotDocs    array  current scan rows keyed by document_type
 *   $photoPath   string current contractors.photo_path
 *   $errors      array  field => message
 *
 * Every document is a number + expiry pair on the master, with one current scan
 * per document here. Uploading to a slot replaces the previous file.
 */

$visaTypes = contractorVisaTypes();
$docSlots  = contractorDocSlots();

/** One field of the compliance grid. */
$cf = function ($name, $label, $type = 'text', $options = [], $placeholder = '') use ($contractor, $errors) {
    $val = old($name, $contractor[$name] ?? '');
    $hasError = isset($errors[$name]);
    $fieldCls = 'w-full px-3 py-2 border rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none '
        . ($hasError ? 'border-red-400' : 'border-slate-200')
        . ($type === 'text' ? ' doc-number' : '');

    echo '<div class="' . ($hasError ? 'lg:col-span-1' : 'lg:col-span-1') . '">';
    echo '<label class="block text-xs font-semibold text-slate-500 mb-1" for="' . $name . '">' . $label . '</label>';

    if ($type === 'select') {
        echo '<select name="' . $name . '" id="' . $name . '" class="' . $fieldCls . '">';
        echo '<option value="">— Select —</option>';
        foreach ($options as $k => $l) {
            echo '<option value="' . htmlspecialchars($k) . '" ' . ((string) $val === (string) $k ? 'selected' : '') . '>' . htmlspecialchars($l) . '</option>';
        }
        echo '</select>';
    } else {
        echo '<input type="' . $type . '" id="' . $name . '" name="' . $name . '" value="' . htmlspecialchars($val) . '"';
        if ($placeholder !== '') echo ' placeholder="' . htmlspecialchars($placeholder) . '"';
        echo ' class="' . $fieldCls . '">';
    }

    if ($hasError) {
        echo '<p class="text-xs text-red-600 mt-1">' . htmlspecialchars($errors[$name]) . '</p>';
    }
    echo '</div>';
};
?>

<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 mb-5">
    <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
        <i class="fas fa-id-card-clip text-indigo-500"></i> Identification &amp; Right to Work
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <?php $cf('nationality', 'Nationality'); ?>
        <?php $cf('emirates_id_number', 'Emirates ID Number', 'text', [], '784-1234-5678901-2'); ?>
        <?php $cf('emirates_id_expiry', 'Emirates ID Expiry', 'date'); ?>

        <?php $cf('passport_number', 'Passport Number'); ?>
        <?php $cf('passport_expiry', 'Passport Expiry', 'date'); ?>
        <?php $cf('visa_type', 'Visa Type', 'select', $visaTypes); ?>
        <?php $cf('visa_number', 'Visa Number'); ?>

        <?php $cf('visa_expiry', 'Visa Expiry', 'date'); ?>
        <?php $cf('work_permit_number', 'Work Permit Number'); ?>
        <?php $cf('work_permit_expiry', 'Work Permit Expiry', 'date'); ?>
        <?php $cf('labor_card_number', 'Labour Card Number'); ?>

        <?php $cf('labor_card_expiry', 'Labour Card Expiry', 'date'); ?>
    </div>

    <p class="text-xs text-slate-500 -mt-2 mb-4">
        <i class="fas fa-circle-info mr-1"></i>
        Expiry dates drive the compliance alerts on the register: past due shows red, inside 60 days shows amber.
    </p>

    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide mb-3">Document Scans</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:col-span-2 gap-4">
        <?php foreach ($docSlots as $slot => $label): ?>
        <?php
            $doc = $slotDocs[$slot] ?? null;
            $expiryColumn = contractorSlotExpiryColumn($slot);
            $expiry = $expiryColumn ? ($contractor[$expiryColumn] ?? '') : '';
            $note = contractorExpiryNote($expiry);
        ?>
        <div class="border border-slate-200 rounded-lg p-4">
            <div class="flex items-start justify-between gap-2 mb-2">
                <label class="block text-xs font-semibold text-slate-500" for="<?= $slot ?>"><?= $label ?></label>
                <span class="text-[10px] px-2 py-0.5 rounded-full whitespace-nowrap <?= $note['class'] ?>"><?= $note['label'] ?></span>
            </div>

            <?php if ($doc): ?>
                <?php if (strpos($doc['mime_type'] ?? '', 'image/') === 0): ?>
                <img src="<?= attachmentFileUrl($doc['file_path']) ?>" alt="<?= htmlspecialchars($label) ?>"
                    class="w-full h-24 object-cover rounded border border-slate-200 mb-2">
                <?php endif; ?>
                <div class="flex items-center gap-2 flex-wrap mb-2">
                    <?= attachmentViewButton('View current file', $doc['file_path'], $doc['mime_type'] ?? '', $label . ' — ' . $doc['document_name']) ?>
                    <span class="text-[11px] text-slate-400">
                        <?= htmlspecialchars($doc['document_name']) ?> &middot; <?= attachmentSizeLabel($doc['file_size']) ?>
                    </span>
                </div>
                <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" name="remove_<?= $slot ?>" value="1" class="rounded border-slate-300 text-indigo-600">
                    Remove current file
                </label>
            <?php else: ?>
                <p class="text-[11px] text-slate-400 mb-2">No scan on record</p>
            <?php endif; ?>

            <input type="file" id="<?= $slot ?>" name="<?= $slot ?>" accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx"
                class="mt-1 block w-full text-xs text-slate-600 file:mr-3 file:px-3 file:py-1 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            <?php if (!empty($errors[$slot])): ?>
            <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors[$slot]) ?></p>
            <?php endif; ?>
            <p class="text-[11px] text-slate-400 mt-1">PDF, JPG, PNG, GIF or DOC &middot; max 5 MB &middot; uploading replaces the file</p>
        </div>
        <?php endforeach; ?>

        <div class="border border-slate-200 rounded-lg p-4">
            <label class="block text-xs font-semibold text-slate-500 mb-2" for="photo">Profile Photo</label>
            <div class="flex items-center gap-3 mb-2">
                <?php if ($photoPath): ?>
                <img src="<?= attachmentFileUrl($photoPath) ?>" alt="" class="h-14 w-14 rounded-full object-cover border border-slate-200">
                <div>
                    <?= attachmentViewButton('View', $photoPath, attachmentMimeFromPath($photoPath), 'Profile Photo') ?>
                    <label class="mt-1 flex items-center gap-2 text-xs text-slate-600">
                        <input type="checkbox" name="remove_photo" value="1" class="rounded border-slate-300 text-indigo-600"> Remove
                    </label>
                </div>
                <?php else: ?>
                <div class="h-14 w-14 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                    <i class="fas fa-user"></i>
                </div>
                <p class="text-[11px] text-slate-400">No photo on record</p>
                <?php endif; ?>
            </div>
            <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.gif"
                class="mt-1 block w-full text-xs text-slate-600 file:mr-3 file:px-3 file:py-1 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            <?php if (!empty($errors['photo'])): ?>
            <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['photo']) ?></p>
            <?php endif; ?>
            <p class="text-[11px] text-slate-400 mt-1">JPG, PNG or GIF &middot; max 2 MB &middot; shown as the register avatar</p>
        </div>
    </div>
</div>
