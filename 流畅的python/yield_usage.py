def countdown(n):
    print('倒计时：%s' % n)
    while n > 0:
        yield n
        n -= 1
    return

c = countdown(10)

for i in c:
    print(i)