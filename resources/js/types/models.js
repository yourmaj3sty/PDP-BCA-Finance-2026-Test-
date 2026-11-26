/**
 * @typedef {'sales_dealer' | 'marketing' | 'atasan_marketing' | 'admin_backoffice'} UserRole
 */

/**
 * @typedef {'DRAFT_DEALER' | 'SUBMITTED' | 'REJECTED' | 'APPROVED' | 'SIGNED' | 'DISBURSED'} ApplicationStatus
 */

/**
 * @typedef {'Belum Menikah' | 'Menikah' | 'Cerai'} MaritalStatus
 */

/**
 * @typedef {'All Risk' | 'TLO' | 'Kombinasi'} InsuranceType
 */

/**
 * @typedef {Object} User
 * @property {number} id
 * @property {string} name
 * @property {string | null} username
 * @property {string} email
 * @property {UserRole} role
 * @property {string} role_label
 */

/**
 * @typedef {Object} ApplicationDocument
 * @property {number} id
 * @property {string} document_type
 * @property {string} document_type_label
 * @property {string} original_name
 * @property {string} file_url
 * @property {boolean} is_image
 * @property {boolean} is_pdf
 * @property {string | null} uploaded_by
 * @property {string} created_at
 * @property {string} updated_at
 */

/**
 * @typedef {Object} ApplicationLog
 * @property {number} id
 * @property {string} action
 * @property {string | null} from_status
 * @property {string | null} from_status_label
 * @property {string} to_status
 * @property {string} to_status_label
 * @property {string | null} notes
 * @property {string} user_name
 * @property {string} user_role_label
 * @property {string} created_at
 */

/**
 * @typedef {Object} LoanBreakdown
 * @property {number} principal_vehicle
 * @property {number} insurance_rate_percent
 * @property {number} insurance_premium
 * @property {number} total_principal
 * @property {number} annual_interest_rate
 * @property {number} total_interest
 * @property {number} total_loan
 * @property {number} monthly_installment
 */

/**
 * @typedef {Object} ApplicationPermissions
 * @property {boolean} can_update
 * @property {boolean} can_submit
 * @property {boolean} can_approve
 * @property {boolean} can_reject
 * @property {boolean} can_view_documents
 * @property {boolean} can_send_po
 * @property {boolean} can_sign
 * @property {boolean} can_disburse
 */

/**
 * @typedef {Object} CreditApplication
 * @property {number} id
 * @property {string} application_number
 * @property {ApplicationStatus} status
 * @property {string} status_label
 * @property {string} customer_name
 * @property {string} customer_nik
 * @property {string} customer_dob
 * @property {MaritalStatus} marital_status
 * @property {string | null} spouse_name
 * @property {string | null} spouse_nik
 * @property {string} dealer_name
 * @property {string} vehicle_brand
 * @property {string} vehicle_model
 * @property {string} vehicle_type
 * @property {string} vehicle_color
 * @property {number} vehicle_price
 * @property {InsuranceType | null} insurance_type
 * @property {number} insurance_premium
 * @property {number | null} down_payment
 * @property {number | null} tenor_months
 * @property {number} interest_rate
 * @property {number | null} monthly_installment
 * @property {LoanBreakdown | null} loan_breakdown
 * @property {string | null} contract_sign_token
 * @property {string | null} signed_at
 * @property {string | null} signature_url
 * @property {string | null} po_sent_at
 * @property {string | null} disbursement_ref
 * @property {string | null} disbursed_at
 * @property {string | null} rejection_note
 * @property {{ id: number, name: string, role_label: string } | null} [creator]
 * @property {{ id: number, name: string, role_label: string } | null} [approver]
 * @property {ApplicationDocument[]} [documents]
 * @property {ApplicationLog[]} [logs]
 * @property {ApplicationPermissions | null} [permissions]
 * @property {string} created_at
 * @property {string} updated_at
 */

/**
 * @typedef {Object} DashboardSummary
 * @property {number} draft_dealer
 * @property {number} waiting_review
 * @property {number} rejected
 * @property {number} approved
 * @property {number} ready_disburse
 * @property {number} completed
 * @property {number} total
 */

// File ini hanya berisi typedef JSDoc; export kosong menjadikannya modul ES.
export {};

