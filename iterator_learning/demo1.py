def fib():
    current,nxt = 0,1
    while True:
        current += nxt
        yield current

r = fib()

for n in r:
    if n>100:
        break
