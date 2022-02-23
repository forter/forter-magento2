import { PaymentType } from '../constants';

export class CheckoutFormDataDto {
    public email: string;
    public firstName: string;
    public lastName: string;
    public streetAddress: string;
    public country: string;
    public city: string;
    public zipcode: string;
    public phone: string;
    public payment:PaymentType;
    public creditCardNumber = '4111111111111111';
    public readonly creditCardExpire = '03/2030';
    public readonly creditCardCVV = '737';
    constructor(email: string,
        firstName: string,
        lastName: string,
        streetAddress: string,
        country: string,
        city: string,
        zipcode: string,
        phone: string,
        payment:PaymentType,
        creditCard?: string) {
        this.email = email;
        this.firstName = firstName;
        this.lastName = lastName;
        this.country = country;
        this.streetAddress = streetAddress;
        this.city = city;
        this.zipcode = zipcode;
        this.phone = phone
        this.payment = payment;
        if (creditCard) {
            this.creditCardNumber = creditCard;
        }
    }
}