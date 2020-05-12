def test():
    return 1,2

def te(a=0,b=0):
    print(a,b)

te(*test())