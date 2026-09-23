<?php
declare(strict_types=1);
require_once __DIR__.'/site-chrome.php';

function apField(string $label,string $name,string $value='',int $max=200,string $type='text',bool $required=true,string $placeholder=''): void {
    if(isset($_POST[$name])&&is_string($_POST[$name]))$value=$_POST[$name];
    echo '<label>'.e($label).($required?' <span class="u-required">*</span>':' <span class="u-optional">Optional</span>').'<input name="'.e($name).'" type="'.$type.'" maxlength="'.$max.'" value="'.e($value).'"'.($required?' required':'').($placeholder!==''?' placeholder="'.e($placeholder).'"':'').'></label>';
}
function apSelect(string $label,string $name,array $options,string $value='',bool $required=true,string $placeholder='-- Select --'): void {
    if(isset($_POST[$name])&&is_string($_POST[$name]))$value=$_POST[$name];
    echo '<label>'.e($label).($required?' <span class="u-required">*</span>':' <span class="u-optional">Optional</span>').'<select name="'.e($name).'"'.($required?' required':'').'><option value="">'.e($placeholder).'</option>';
    foreach($options as $opt)echo '<option value="'.e($opt).'"'.($value===$opt?' selected':'').'>'.e($opt).'</option>';
    echo '</select></label>';
}
function apArea(string $label,string $name,string $value='',int $max=300,int $rows=3,string $placeholder='',bool $required=false): void {
    if(isset($_POST[$name])&&is_string($_POST[$name]))$value=$_POST[$name];
    echo '<label>'.e($label).($required?' <span class="u-required">*</span>':' <span class="u-optional">Optional</span>').'<textarea name="'.e($name).'" maxlength="'.$max.'" rows="'.$rows.'"'.($placeholder!==''?' placeholder="'.e($placeholder).'"':'').($required?' required':'').'>'.e($value).'</textarea></label>';
}
function apYearOptions(): array {$years=[];for($y=(int)date('Y');$y>=1950;$y--)$years[]=(string)$y;return $years;}
function apPct(string $label,string $name,string $value=''): void {
    if(isset($_POST[$name])&&is_string($_POST[$name]))$value=$_POST[$name];
    echo '<label>'.e($label).' <span class="u-optional">Auto-calculated</span><input name="'.e($name).'" type="text" maxlength="6" value="'.e($value).'" readonly tabindex="-1"></label>';
}
function apFileSlot(string $label,string $name,string $hint=''): void {
    echo '<div class="w-doc"><span class="w-doc-label">'.e($label).'</span><input type="file" name="'.e($name).'" accept=".pdf,.jpg,.jpeg,.png">'.($hint!==''?'<small>'.e($hint).'</small>':'').'</div>';
}
function applicationFormFields(array $data,string $privacy,string $mode='apply'): void {
    $step=min(4,max(1,(int)($_POST['wizard_step']??1)));
    $titles=['Personal Details','Academic Details','Upload Documents','Preview & Submit'];
    echo '<ol class="w-steps" aria-label="Application steps">';
    foreach($titles as $i=>$title){$n=$i+1;echo '<li data-wdot="'.$n.'" class="'.($n<$step?'done':($n===$step?'active':'')).'"><span class="w-num">'.$n.'</span><span class="w-title">'.e($title).'</span></li>';}
    echo '</ol><input type="hidden" name="wizard_step" value="'.$step.'">';
    echo '<fieldset class="w-step'.($step===1?' active':'').'" data-wstep="1"><legend>Step 1 — Personal Details</legend><div class="u-form-grid">';
    apField('Full Name','name',(string)($data['name']??''),120,'text',true,'Enter full name (as per 10th certificate)');
    apField("Father's Name",'father_name',(string)($data['father_name']??''),120,'text',true,"Enter father's name");
    apField("Mother's Name",'mother_name',(string)($data['mother_name']??''),120,'text',true,"Enter mother's name");
    apField('Date of Birth','date_of_birth',(string)($data['date_of_birth']??''),10,'date',true);
    apSelect('Gender','gender',['Male','Female','Other'],(string)($data['gender']??''),true,'-- Select Gender --');
    apSelect('Category','category',['General','EWS','OBC-A','OBC-B','SC','ST','Other'],(string)($data['category']??''),true,'-- Select Category --');
    apSelect('Nationality','nationality',['Indian','Other'],(string)($data['nationality']??'Indian'),true,'-- Select Nationality --');
    apField('Aadhaar Number','aadhaar',(string)($data['aadhaar']??''),12,'text',false,'12-digit (Optional)');
    apSelect('Religion','religion',['Hinduism','Islam','Christianity','Sikhism','Buddhism','Jainism','Other'],(string)($data['religion']??''),false,'-- Select Religion --');
    apSelect('Blood Group','blood_group',['A+','A-','B+','B-','AB+','AB-','O+','O-'],(string)($data['blood_group']??''),false,'-- Select Blood Group --');
    echo '</div><h3 class="w-sub">Contact &amp; Family</h3><div class="u-form-grid">';
    apField('Mobile Number','phone',(string)($data['phone']??''),30,'tel',true,'10-digit mobile number');
    apField('City / Town','city',(string)($data['city']??''),100,'text',true,'Enter city');
    apField('State','state',(string)($data['state']??''),100,'text',false,'Enter state');
    apField('PIN Code','pincode',(string)($data['pincode']??''),10,'text',false,'6-digit PIN');
    apField('Parent / Guardian Name','guardian_name',(string)($data['guardian_name']??''),120,'text',false,"Guardian's full name");
    echo '</div>';
    apArea('Full Correspondence Address','address',(string)($data['address']??''),300,3,'House, street, area');
    echo '<div class="w-nav"><span></span><button class="u-btn solid" type="button" data-wnext>Save &amp; Next →</button></div></fieldset>';
    echo '<fieldset class="w-step'.($step===2?' active':'').'" data-wstep="2"><legend>Step 2 — Academic Details</legend><h3 class="w-sub">10th (Madhyamik / Equivalent)</h3><div class="u-form-grid">';
    apField('Board','board_10',(string)($data['board_10']??''),120,'text',true,'e.g. W.B.B.S.E.');
    apSelect('Year of Passing','year_10',apYearOptions(),(string)($data['year_10']??''),true,'-- Select Year --');
    apField('Roll Number','roll_10',(string)($data['roll_10']??''),60,'text',true,'As printed on marksheet');
    apField('Total Marks','total_10',(string)($data['total_10']??''),7,'text',true,'e.g. 700');
    apField('Marks Obtained','obtained_10',(string)($data['obtained_10']??''),7,'text',true,'e.g. 605');
    apPct('Percentage (%)','percentage_10',(string)($data['percentage_10']??''));
    echo '</div><h3 class="w-sub">12th (Higher Secondary / Equivalent)</h3><div class="u-form-grid">';
    apField('Board','board_12',(string)($data['board_12']??''),120,'text',true,'e.g. W.B.C.H.S.E.');
    apSelect('Year of Passing','year_12',apYearOptions(),(string)($data['year_12']??''),true,'-- Select Year --');
    apField('Stream','stream_12',(string)($data['stream_12']??''),60,'text',true,'e.g. Science');
    apField('Roll Number','roll_12',(string)($data['roll_12']??''),60,'text',true,'As printed on marksheet');
    apField('Total Marks','total_12',(string)($data['total_12']??''),7,'text',true,'e.g. 500');
    apField('Marks Obtained','obtained_12',(string)($data['obtained_12']??''),7,'text',true,'e.g. 413');
    apPct('Percentage (%)','percentage_12',(string)($data['percentage_12']??''));
    echo '</div><h3 class="w-sub">Highest Qualification &amp; Entrance</h3><div class="u-form-grid">';
    apField('Highest Completed Qualification','qualification',(string)($data['qualification']??''),300,'text',true,'e.g. Higher secondary science');
    apSelect('Completion / Passing Year','completion_year',apYearOptions(),(string)($data['completion_year']??''),true,'-- Select Year --');
    apField('Board / University','board_university',(string)($data['board_university']??''),160,'text',false,'If different from above');
    apField('Entrance Examination','entrance_exam',(string)($data['entrance_exam']??''),100,'text',false,'Only if applicable');
    apField('Entrance Rank / Score','entrance_rank',(string)($data['entrance_rank']??''),30,'text',false,'Only if applicable');
    echo '</div><div class="w-nav"><button class="u-btn ghost" type="button" data-wprev>← Previous</button><button class="u-btn solid" type="button" data-wnext>Save &amp; Next →</button></div></fieldset>';
    echo '<fieldset class="w-step'.($step===3?' active':'').'" data-wstep="3"><legend>Step 3 — Upload Documents</legend>';
    if(function_exists('wizardUploadsReady')&&wizardUploadsReady()){
        echo '<p class="w-note-pink">Only PDF / JPG / PNG files are allowed. Maximum file size: 2 MB per file.</p><div class="w-docs">';
        apFileSlot('Photograph *','doc_photo','Recent passport size photo');
        apFileSlot('Signature *','doc_signature','Sign in black or blue ink on white paper');
        apFileSlot('Class 10 Marksheet *','doc_marksheet10','Madhyamik or equivalent marksheet');
        apFileSlot('Class 12 Marksheet *','doc_marksheet12','Higher secondary or equivalent marksheet');
        apFileSlot('Caste Certificate','doc_caste','If applicable');
        apFileSlot('Disability (PwD) Certificate','doc_pwd','If applicable');
        apFileSlot('Domicile Certificate','doc_domicile','If required');
        apFileSlot('Any Other Document','doc_other','If any');
        echo '</div><p class="u-small">Documents are optional at this stage — anything you attach is stored securely for office verification, and you can add more later from your application page. Files already attached to a saved draft are kept.</p>';
    }else{
        echo '<p class="w-note-pink">Document upload is not enabled for this installation yet. Submit your details now; the office will tell you how to send documents.</p>';
    }
    echo '<div class="w-nav"><button class="u-btn ghost" type="button" data-wprev>← Previous</button><button class="u-btn solid" type="button" data-wnext>Save &amp; Next →</button></div></fieldset>';
    echo '<fieldset class="w-step'.($step===4?' active':'').'" data-wstep="4"><legend>Step 4 — Preview &amp; Submit</legend>';
    echo '<div class="w-preview" id="wPreview"><p class="u-small">Your entered details appear here for verification before you submit.</p></div>';
    apArea('Additional Information','note',(string)($data['note']??''),1500,4,'Do not enter PAN, medical details, passwords or OTPs.');
    echo '<details open><summary>Institute application privacy notice</summary><p>'.nl2br(e($privacy)).'</p></details>';
    echo '<label class="u-check"><input type="checkbox" name="consent" value="yes" required> I hereby declare that all the information given above is true to the best of my knowledge. I have read the notice and consent to processing this application. Admission requires eligibility verification and office approval.</label>';
    echo '<div class="w-nav"><button class="u-btn ghost" type="button" data-wprev>← Previous</button><span class="w-final">';
    if($mode==='apply')echo '<button class="u-btn ghost" type="submit" name="submit_action" value="save_draft" formnovalidate>Save draft</button> <button class="u-btn solid" type="submit" name="submit_action" value="submit_final">Submit Application ✓</button>';
    else echo '<button class="u-btn solid" type="submit">Resubmit corrected application →</button>';
    echo '</span></div></fieldset>';
}
