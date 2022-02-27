export const declineEmail = 'decline@forter.com'
export const acceptEmail = 'approve@forter.com'
export const notReviewEmail = 'notreviewed@forter.com'
export const TextOrderErrorMsg = "We are sorry, but we could not process your order at this time."
export const TextOrderSuccessMsg ="Thank you for your purchase!"
export const TextNoDataOnTable = "We couldn't find any records."
export enum PaymentType {
    BrainTree,
    Paypal,
    Adyen
}
export enum ForterFlowMode {
    Before=1,
    After=2,
    BeforeAndAfter=4,
    Cron=3
}