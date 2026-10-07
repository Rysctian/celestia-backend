# Payroll Cut-Off

## Schema

### payroll_cutoffs

- id
- schedule_type
- quarter
- start_date
- end_date
- no_dtr
- dtr_cutoff_from
- dtr_cutoff_to
- dtr_confirmation_start
- dtr_confirmation_end
- dtr_confirmation_time_from
- dtr_confirmation_time_to
- is_active
- created_at
- updated_at

### payroll_cutoff_releases

- id
- payroll_cutoff_id
- cutoff_no
- release_date
- created_at
- updated_at

Relationship:

payroll_cutoffs
→ has many payroll_cutoff_releases

## Process

1. Create payroll cut-off.

2. Set payroll period.
   - start date
   - end date

3. Set schedule type.
   - monthly
   - semi-monthly

4. Set quarter if needed.

5. Set DTR cutoff.
   - date from
   - date to
   - if No DTR is checked, DTR cutoff dates can be empty

6. Set DTR confirmation window.
   - start date
   - end date
   - time from
   - time to

7. Set payroll release dates.
   - 1st cutoff release date
   - 2nd cutoff release date

8. Save cut-off.

9. Process employee attendance inside the cut-off period.

10. Apply approved attendance-related applications.
    - leave
    - official business
    - attendance correction
    - overtime
    - schedule changes

11. Finish DTR processing before the DTR cutoff.

12. Allow DTR review and confirmation during the confirmation window.

13. Confirm or lock attendance.

14. Use confirmed attendance for payroll computation.

15. Release payroll based on the configured release dates.

Important:

- DTR cutoff and DTR confirmation are different.
- DTR cutoff = deadline / attendance processing period.
- DTR confirmation = review and confirmation period.
- Do not include employment type.
