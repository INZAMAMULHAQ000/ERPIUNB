<?php
/**
 * Shared form rendering for the contractor module's descriptor-driven forms
 * (contractor master and contract). Each form declares a $sections array of
 * field descriptors and hands it to contractorRenderSection().
 *
 * Descriptor keys: n (name), l (label), t (text|email|tel|date|number|select|
 * checkbox|textarea), span (1-4 grid columns), opts, blank, req, ph, hint,
 * step, min, max.
 */

function contractorFieldValue($name, array $record)
{
    return old($name, (string) ($record[$name] ?? ''));
}

function contractorInputClass($name, array $errors, $extra = '')
{
    return 'w-full px-3 py-2 border rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none '
        . (isset($errors[$name]) ? 'border-red-400' : 'border-slate-200') . $extra;
}

/**
 * Echo one card. $record supplies stored values, $errors field messages, and
 * $locked disables every control (used for approved or spent contracts).
 */
function contractorRenderSection(array $sec, array $record, array $errors, $locked = false)
{
    $spanCls = [1 => 'lg:col-span-1', 2 => 'lg:col-span-2', 3 => 'lg:col-span-3', 4 => 'lg:col-span-4'];
    $esc = fn($v) => htmlspecialchars((string) $v);
    $readonlyNote = '<span class="text-[10px] text-slate-400 uppercase tracking-wide ml-2">read-only</span>';
    ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 mb-5">
        <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
            <i class="fas <?= $sec['icon'] ?> text-indigo-500"></i> <?= $esc($sec['title']) ?>
            <?php if ($locked && !empty($sec['lockable'])) echo $readonlyNote; ?>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($sec['fields'] as $f):
                $name = $f['n'];
                $val  = contractorFieldValue($name, $record);
                $span = $spanCls[$f['span'] ?? 1];
                $req  = !empty($f['req']) ? ' <span class="text-red-500">*</span>' : '';
                $dis  = $locked ? 'disabled' : '';
                $errHtml = isset($errors[$name])
                    ? '<p class="text-xs text-red-600 mt-1">' . $esc($errors[$name]) . '</p>' : '';
                $hintHtml = !empty($f['hint'])
                    ? '<p class="text-[11px] text-slate-400 mt-1">' . $esc($f['hint']) . '</p>' : '';
                ?>

                <?php if ($f['t'] === 'checkbox'): ?>
                <label class="<?= $span ?> flex items-start gap-2.5 p-3 bg-slate-50 rounded-lg <?= $locked ? 'opacity-60' : 'cursor-pointer hover:bg-slate-100' ?> transition">
                    <input type="checkbox" name="<?= $name ?>" value="1" <?= !empty($val) ? 'checked' : '' ?> <?= $dis ?>
                           class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm text-slate-700 leading-snug"><?= $esc($f['l']) ?>
                        <?php if (!empty($f['hint'])): ?><span class="block text-[11px] text-slate-400"><?= $esc($f['hint']) ?></span><?php endif; ?>
                    </span>
                </label>

                <?php elseif ($f['t'] === 'textarea'): ?>
                <div class="<?= $span ?>">
                    <label class="block text-xs font-semibold text-slate-500 mb-1" for="<?= $name ?>"><?= $esc($f['l']) ?><?= $req ?></label>
                    <textarea name="<?= $name ?>" id="<?= $name ?>" rows="<?= $f['rows'] ?? 3 ?>"
                              <?= $dis ?> class="<?= contractorInputClass($name, $errors) ?>"><?= $esc($val) ?></textarea>
                    <?= $hintHtml . $errHtml ?>
                </div>

                <?php elseif ($f['t'] === 'select'): ?>
                <div class="<?= $span ?>">
                    <label class="block text-xs font-semibold text-slate-500 mb-1" for="<?= $name ?>"><?= $esc($f['l']) ?><?= $req ?></label>
                    <select name="<?= $name ?>" id="<?= $name ?>" <?= $dis ?> class="<?= contractorInputClass($name, $errors) ?>">
                        <option value=""><?= $f['blank'] ?? '— Select —' ?></option>
                        <?php foreach ($f['opts'] as $k => $l): ?>
                        <option value="<?= $esc($k) ?>" <?= (string) $val === (string) $k ? 'selected' : '' ?>><?= $esc($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $hintHtml . $errHtml ?>
                </div>

                <?php elseif ($f['t'] === 'static'): ?>
                <div class="<?= $span ?>">
                    <label class="block text-xs font-semibold text-slate-500 mb-1"><?= $esc($f['l']) ?></label>
                    <p class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-mono text-slate-600"><?= $esc($val) ?></p>
                    <?= $hintHtml ?>
                </div>

                <?php else: ?>
                <div class="<?= $span ?>">
                    <label class="block text-xs font-semibold text-slate-500 mb-1" for="<?= $name ?>"><?= $esc($f['l']) ?><?= $req ?></label>
                    <input type="<?= $f['t'] ?>" name="<?= $name ?>" id="<?= $name ?>" value="<?= $esc($val) ?>"
                           <?= isset($f['ph']) ? 'placeholder="' . $esc($f['ph']) . '"' : '' ?>
                           <?= isset($f['step']) ? 'step="' . $esc($f['step']) . '"' : '' ?>
                           <?= isset($f['min']) ? 'min="' . $esc($f['min']) . '"' : '' ?>
                           <?= isset($f['max']) ? 'max="' . $esc($f['max']) . '"' : '' ?>
                           <?= $dis ?> class="<?= contractorInputClass($name, $errors, isset($f['jscls']) ? ' ' . $f['jscls'] : '') ?>">
                    <?= $hintHtml . $errHtml ?>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
