def gen_func():
    html = yield "www.baidu.com"
    print(html)
    yield 2
    return "bobby"


if __name__ == '__main__':
    gen = gen_func()
    url = next(gen)
    html = "www.qq.com"
    gen.send(html)#send 可以传递值进入生成器内部，同时重启生成器执行到下一个yield位置
