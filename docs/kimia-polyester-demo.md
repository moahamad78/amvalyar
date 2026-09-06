# دموی شرکت کیمیا پلی‌استر قم

این دمو با کد شرکت `KIMIA-DEMO` از آیین‌نامه `KPQ-FI-WI-001` الگوبرداری شده است.
تمام نام‌های پرسنلی، ایمیل‌ها، شماره‌ها، قیمت‌ها و مشخصات اموال ساختگی‌اند و نباید
به‌عنوان اطلاعات واقعی شرکت تلقی شوند.

## ساخت یا همگام‌سازی دمو

ابتدا رمز مشترک کاربران دمو را در محیط اجرا تعریف کنید:

```text
KIMIA_DEMO_PASSWORD=<a unique password with at least 12 characters>
```

سپس در Render Shell اجرا کنید:

```text
php artisan demo:provision-kimia --force
```

این فرمان تکرارپذیر است و اجرای مجدد آن کاربران، ساختار و دارایی‌های دمو را همگام
می‌کند، بدون اینکه ۱۰۰۰ دارایی یا تراکنش تحویل تکراری ساخته شود.

## حساب‌های ورود

نام کاربری همه حساب‌ها با `kimia.` شروع می‌شود. نمونه‌ها:

- `kimia.company.admin`
- `kimia.chief.asset.keeper`
- `kimia.finance.manager`
- `kimia.inspection.head`
- `kimia.warehouse.supervisor`
- `kimia.employee`

همه حساب‌ها از رمز `KIMIA_DEMO_PASSWORD` استفاده می‌کنند. پیش از تحویل دمو به
مشتری، برای حساب‌های مدیریتی رمزهای مستقل تنظیم شود.
