<?php
/**
 * Shared identification + document-scan block for the employee create/edit
 * forms. Included by create.php and edit.php, which set:
 *
 *   $isEdit     bool   — edit mode (drives the "current file" column)
 *   $employeeId int    — 0 on create
 *   $slotDocs   array  — current document rows keyed by slot
 *   $photoPath  string — current employees.photo_path, '' when none
 */

$visaTypes = employeeVisaTypes();
$docSlots  = employeeDocSlots();
?>

<div>
    <h3 class="text-lg font-medium text-gray-800 mb-3 border-b pb-2">Identification</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nationality</label>
            <input type="text" id="nationality" name="nationality" value="<?= old('nationality', $emp['nationality'] ?? '') ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Passport Number</label>
            <input type="text" id="passport_number" name="passport_number" value="<?= old('passport_number', $emp['passport_number'] ?? '') ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Passport Expiry</label>
            <input type="date" id="passport_expiry" name="passport_expiry" value="<?= old('passport_expiry', $emp['passport_expiry'] ?? '') ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Visa Type</label>
            <select id="visa_type" name="visa_type" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                <option value="">Select</option>
                <?php foreach ($visaTypes as $key => $label): ?>
                <option value="<?= $key ?>" <?= old('visa_type', $emp['visa_type'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Visa Number</label>
            <input type="text" id="visa_number" name="visa_number" value="<?= old('visa_number', $emp['visa_number'] ?? '') ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Visa Expiry</label>
            <input type="date" id="visa_expiry" name="visa_expiry" value="<?= old('visa_expiry', $emp['visa_expiry'] ?? '') ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Emirates ID Number</label>
            <input type="text" id="emirates_id_number" name="emirates_id_number" value="<?= old('emirates_id_number', $emp['emirates_id_number'] ?? '') ?>"
                placeholder="784123456789012" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Emirates ID Expiry</label>
            <input type="date" id="emirates_id_expiry" name="emirates_id_expiry" value="<?= old('emirates_id_expiry', $emp['emirates_id_expiry'] ?? '') ?>"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
    </div>

    <h4 class="text-sm font-semibold text-gray-700 mt-5 mb-3">Employment Contract</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Contract Number</label>
            <input type="text" id="contract_number" name="contract_number" value="<?= old('contract_number', $emp['contract_number'] ?? '') ?>"
                   placeholder="MOHRE-2026-00014" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Contract Start Date</label>
            <input type="date" id="contract_start_date" name="contract_start_date" value="<?= old('contract_start_date', $emp['contract_start_date'] ?? '') ?>"
                   class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Contract End Date</label>
            <input type="date" id="contract_end_date" name="contract_end_date" value="<?= old('contract_end_date', $emp['contract_end_date'] ?? '') ?>"
                   class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            <?php if (!empty($errors['contract_end_date'])): ?>
            <p class="text-red-600 text-sm mt-1"><?= sanitize($errors['contract_end_date']) ?></p>
            <?php endif; ?>
            <?php if (!empty($errors['contract_start_date'])): ?>
            <p class="text-red-600 text-sm mt-1"><?= sanitize($errors['contract_start_date']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div>
    <h3 class="text-lg font-medium text-gray-800 mb-1 border-b pb-2">Document Scans &amp; Photo</h3>
    <p class="text-sm text-gray-500 mb-4">
        <?= $isEdit ? 'Uploading a new file replaces the one on record.' : 'You can add these now or later from the employee profile.' ?>
    </p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach ($docSlots as $slot => $label): ?>
        <?php
            $doc = $slotDocs[$slot] ?? null;
            $expiryColumn = employeeSlotExpiryColumn($slot);
            $expiry = $expiryColumn ? ($emp[$expiryColumn] ?? '') : '';
        ?>
        <div class="border rounded-lg p-4">
            <div class="flex items-start justify-between gap-2 mb-1">
                <label class="block text-sm font-medium text-gray-700" for="<?= $slot ?>"><?= $label ?></label>
                <?php if ($expiry): ?>
                <span class="text-[11px] px-2 py-0.5 rounded-full <?= employeeExpiryChipClass($expiry) ?>">
                    <?= date('M d, Y', strtotime($expiry)) ?>
                </span>
                <?php endif; ?>
            </div>

            <?php if ($doc): ?>
            <?php if (strpos($doc['mime_type'] ?? '', 'image/') === 0): ?>
            <img src="<?= attachmentFileUrl($doc['file_path']) ?>" alt="<?= sanitize($label) ?>"
                class="w-full h-24 object-cover rounded border mb-2">
            <?php endif; ?>
            <div class="flex items-center gap-2 flex-wrap">
                <?= attachmentViewButton('View current file', $doc['file_path'], $doc['mime_type'] ?? '', $label . ' — ' . $doc['document_name']) ?>
                <span class="text-xs text-gray-500">
                    <?= sanitize($doc['document_name']) ?> &middot; <?= attachmentSizeLabel($doc['file_size']) ?>
                </span>
            </div>
            <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="remove_<?= $slot ?>" value="1"> Remove
            </label>
            <?php else: ?>
            <p class="text-xs text-gray-500">No file on record</p>
            <?php endif; ?>

            <input type="file" id="<?= $slot ?>" name="<?= $slot ?>" accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx"
                class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:px-3 file:py-1 file:rounded file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            <?php if (!empty($errors[$slot])): ?>
            <p class="text-red-600 text-sm mt-1"><?= sanitize($errors[$slot]) ?></p>
            <?php endif; ?>
            <p class="text-xs text-gray-500 mt-1">PDF, JPG, PNG, GIF or DOC &middot; max 5 MB</p>
        </div>
        <?php endforeach; ?>

        <div class="border rounded-lg p-4">
            <label class="block text-sm font-medium text-gray-700 mb-1" for="photo">Profile Photo</label>

            <div class="flex items-center gap-3 mb-2">
                <?php if ($photoPath): ?>
                <img src="<?= attachmentFileUrl($photoPath) ?>" alt="" class="h-14 w-14 rounded-full object-cover border">
                <div>
                    <?= attachmentViewButton('View', $photoPath, attachmentMimeFromPath($photoPath), 'Profile Photo') ?>
                    <label class="mt-1 flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="remove_photo" value="1"> Remove
                    </label>
                </div>
                <?php else: ?>
                <div class="h-14 w-14 rounded-full bg-gray-100 flex items-center justify-center text-gray-400">
                    <i class="fas fa-user"></i>
                </div>
                <p class="text-xs text-gray-500">No photo on record</p>
                <?php endif; ?>
            </div>

            <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.gif"
                class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:px-3 file:py-1 file:rounded file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            <?php if (!empty($errors['photo'])): ?>
            <p class="text-red-600 text-sm mt-1"><?= sanitize($errors['photo']) ?></p>
            <?php endif; ?>
            <p class="text-xs text-gray-500 mt-1">JPG, PNG or GIF &middot; max 2 MB &middot; shown as the employee avatar</p>
        </div>
    </div>
</div>
