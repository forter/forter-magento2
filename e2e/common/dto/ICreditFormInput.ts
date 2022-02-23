export interface ICreditFormInput {
    getSelectPaymentType: () => string;
    getPaymentIFrameCreditNum: () => string;
    getCreditCardNum: () => string;
    getPaymentIFrameCreditExp: () => string;
    getCreditCardExp: () => string;
    getPaymentIFrameCreditCVV: () => string;
    getCreditCardCVV: () => string;
}