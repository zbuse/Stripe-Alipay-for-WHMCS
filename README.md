# Stripe-Alipay-for-WHMCS
Stripe Alipay for WHMCS Gateway modules

交易 py ID 存入session付款跳转回账单页面会入账

防止用户支付完直接关掉页面， 建议添加 webhooks 回传更新装

默认stripe 交易货币为 CNY， 设定其他货币请确认账户是否支持。


webhooks 比较烦人， 另外有个想法 需要建立数据库表然后写一个hooks丢给 cron 检测交易订单是否完成然后进行入账操作。

太菜鸡写不动代码
