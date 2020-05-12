import time

def clock(func):
    def clocked(*args):
        t0 = time.perf_counter()
        ret = func(*args)
        elapsed = time.perf_counter() -t0
        name=func.__name__
        arg_str = ','.join(repr(arg) for arg in args)
        print('[%0.8fs] %s(%s) -> %r'%(elapsed,name,arg_str,ret))
        return ret
    return clocked

@clock
def snno(seconds):
    time.sleep(seconds)
if __name__ == '__main__':
    snno(.5)