import express from 'express';
import {checkout} from "@nc/checkout/checkout";
import {login} from "@nc/login/login";
import {log} from "@nc/common/log";

const app = express();
app.get('/', (_req, res) => {

    const resultCheckout = checkout();
    const resultLogin = login();
    log('request')

    res.send({
        checkout: resultCheckout,
        login: resultLogin,
    })
});

app.listen(3000);
console.info('Server started on port http://localhost:3000');
